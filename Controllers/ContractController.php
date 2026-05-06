<?php

include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/RulesController.php');
include_once(__DIR__ . '/../services/OpenRouterService.php');

class ContractController
{
    private PDO $pdo;
    private RulesController $ruleController;
    private OpenRouterService $openRouterService;
    private array $columnCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
        $this->openRouterService = new OpenRouterService($this->pdo);
        $this->ruleController = new RulesController($this->pdo);
    }

    public function getRuleController(): RulesController
    {
        return $this->ruleController;
    }

    private function ensureAiFeatureSchema(): void
    {
        // Schema is owned by Diversity.sql (single source of truth).
        return;
    }

    public function analyzeWithAI(int $contractId, ?int $analyzedBy = null, bool $forceRefresh = false): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM contracts WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $contractId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$contract) {
                throw new RuntimeException('Contract not found');
            }

            $text = trim((string) ($contract['terms'] ?? ''));
            if ($text === '') {
                $text = trim((string) ($contract['description'] ?? ''));
            }

            $analysis = $this->openRouterService->analyzeContractText($text, $contractId, $analyzedBy, $forceRefresh);
            
            // Save to database if columns exist
            if ($contractId > 0 && $this->contractHasColumn('risk_score') && $this->contractHasColumn('analysis_json')) {
                try {
                    $stmt = $this->pdo->prepare('UPDATE contracts SET risk_score = :risk_score, analysis_json = :analysis_json, updated_at = NOW() WHERE id = :id');
                    $stmt->execute([
                        'risk_score' => isset($analysis['risk_score']) && is_numeric($analysis['risk_score']) ? max(0, min(100, (int) $analysis['risk_score'])) : null,
                        'analysis_json' => json_encode($analysis, JSON_UNESCAPED_UNICODE),
                        'id' => $contractId,
                    ]);
                } catch (Throwable $dbEx) {
                    error_log('ContractController::analyzeWithAI DB update failed: ' . $dbEx->getMessage());
                }
            }

            // Handle AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                if (ob_get_length()) ob_clean();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'analysis' => $analysis]);
                exit;
            }

            return $analysis;
        } catch (Throwable $e) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                if (ob_get_length()) ob_clean();
                header('Content-Type: application/json; charset=utf-8', true, 400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            throw $e;
        }
    }

    public function generateProfessionalPDF(int $contractId): string
    {
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        }
        
        $stmt = $this->pdo->prepare('SELECT c.*, o.title AS offer_title, 
                       cu.first_name AS client_first, cu.last_name AS client_last, cu.email AS client_email,
                       fu.first_name AS freelancer_first, fu.last_name AS freelancer_last, fu.email AS freelancer_email
                FROM contracts c 
                LEFT JOIN job_offers o ON o.id = c.job_offer_id 
                LEFT JOIN users cu ON cu.id = c.client_id
                LEFT JOIN users fu ON fu.id = c.freelancer_id
                WHERE c.id = :id LIMIT 1');
        $stmt->execute(['id' => $contractId]);
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contract) {
            throw new RuntimeException('Contract not found');
        }

        $tmpDir = __DIR__ . '/../storage/assistant/tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        $baseName = 'Contract_#' . $contractId . '_' . date('Ymd_His');
        $pdfPath = $tmpDir . '/' . $baseName . '.pdf';

        if (file_exists($pdfPath)) {
            @unlink($pdfPath);
        }

        $clientName = trim(($contract['client_first'] ?? '') . ' ' . ($contract['client_last'] ?? ''));
        $freelancerName = trim(($contract['freelancer_first'] ?? '') . ' ' . ($contract['freelancer_last'] ?? ''));
        $title = ($contract['title'] ?? $contract['offer_title'] ?? 'Digital Agreement');

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Contract #' . $contractId . '</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Poppins", "Helvetica", "Arial", sans-serif; color: #1f2937; line-height: 1.5; margin: 0; padding: 20px 40px; font-size: 14px; }
        .header { border-bottom: 2px solid #e5e7eb; padding-bottom: 20px; margin-bottom: 30px; text-align: center; }
        .header h1 { font-size: 24px; color: #111827; margin: 0; text-transform: uppercase; letter-spacing: 1px; }
        .header p { color: #6b7280; font-size: 12px; margin: 5px 0 0 0; }
        .section { margin-bottom: 30px; }
        .section-title { font-size: 16px; font-weight: bold; color: #4f46e5; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; margin-bottom: 15px; text-transform: uppercase; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .grid td { vertical-align: top; padding: 5px; }
        .label { font-weight: bold; color: #374151; font-size: 12px; text-transform: uppercase; margin-bottom: 4px; display: block; }
        .value { color: #111827; }
        .text-block { background: #f9fafb; border: 1px solid #f3f4f6; border-radius: 8px; padding: 15px; text-align: justify; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th, .table td { border: 1px solid #e5e7eb; padding: 10px; text-align: left; }
        .table th { background: #f3f4f6; font-size: 12px; text-transform: uppercase; color: #374151; }
        .footer { border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 40px; font-size: 10px; color: #9ca3af; text-align: center; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; background: #e0e7ff; color: #4f46e5; border: 1px solid #c7d2fe; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Service Agreement</h1>
        <p>Contract Reference: #' . str_pad((string)$contractId, 6, '0', STR_PAD_LEFT) . ' | Generated: ' . date('F j, Y') . '</p>
    </div>
    
    <div class="section">
        <div class="section-title">Parties</div>
        <table class="grid">
            <tr>
                <td width="50%">
                    <div class="label">Client</div>
                    <div class="value"><strong>' . htmlspecialchars($clientName) . '</strong><br>' . htmlspecialchars($contract['client_email'] ?? 'N/A') . '</div>
                </td>
                <td width="50%">
                    <div class="label">Freelancer</div>
                    <div class="value"><strong>' . htmlspecialchars($freelancerName) . '</strong><br>' . htmlspecialchars($contract['freelancer_email'] ?? 'N/A') . '</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Project Details</div>
        <table class="grid">
            <tr>
                <td width="50%">
                    <div class="label">Project Title</div>
                    <div class="value">' . htmlspecialchars($title) . '</div>
                </td>
                <td width="50%">
                    <div class="label">Status</div>
                    <div class="value"><span class="status-badge">' . htmlspecialchars($contract['status'] ?? 'DRAFT') . '</span></div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Terms & Conditions</div>
        <div class="text-block">
            ' . nl2br(htmlspecialchars($contract['terms'] ?? 'No terms provided.')) . '
        </div>
    </div>

    <div class="section">
        <div class="section-title">Financial Terms</div>
        <table class="table">
            <tr>
                <th width="30%">Total Budget</th>
                <td><strong>' . number_format((float)($contract['amount'] ?? 0), 2) . ' TND</strong></td>
            </tr>
            <tr>
                <th>Payment Method</th>
                <td>' . htmlspecialchars($contract['payment_details'] ?? 'Secured Platform Transfer') . '</td>
            </tr>
            <tr>
                <th>Taxes & Fees</th>
                <td>Taxes Inclusive. Platform Fees Handled by Diversity.is</td>
            </tr>
        </table>
    </div>
';
        
        $rules = $this->ruleController->listByContractId($contractId);
        if (!empty($rules)) {
            $html .= '<div class="section">
        <div class="section-title">Project Milestones</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Milestone</th>
                    <th>Type</th>
                    <th>Due Date</th>
                    <th>Penalty</th>
                </tr>
            </thead>
            <tbody>';
            foreach ($rules as $r) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($r['title'] ?? 'Task') . '</td>
                    <td>' . htmlspecialchars(strtoupper($r['rule_type'] ?? 'OTHER')) . '</td>
                    <td>' . htmlspecialchars($r['due_date'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($r['penalty'] ?? 'Standard Policy') . '</td>
                </tr>';
            }
            $html .= '</tbody>
        </table>
    </div>';
        }

        $html .= '<div class="section">
        <div class="section-title">Signatures & Verification</div>
        <table class="grid">
            <tr>
                <td width="50%">
                    <div class="label">Client Signature</div>
                    <div class="value">';
        if (!empty($contract['client_signed'])) {
            $html .= '<strong style="color: #059669;">VERIFIED</strong><br><span style="font-size: 10px; color: #6b7280; font-family: monospace;">Hash: ' . md5($contract['client_id'] . $contract['signed_at'] . 'CLIENT') . '</span>';
        } else {
            $html .= '<span style="color: #dc2626;">PENDING</span>';
        }
        $html .= '</div>
                </td>
                <td width="50%">
                    <div class="label">Freelancer Signature</div>
                    <div class="value">';
        if (!empty($contract['freelancer_signed'])) {
            $html .= '<strong style="color: #059669;">VERIFIED</strong><br><span style="font-size: 10px; color: #6b7280; font-family: monospace;">Hash: ' . md5($contract['freelancer_id'] . $contract['signed_at'] . 'FREELANCER') . '</span>';
        } else {
            $html .= '<span style="color: #dc2626;">PENDING</span>';
        }
        $html .= '</div>
                </td>
            </tr>
        </table>
        <p style="font-size: 12px; color: #4b5563; margin-top: 15px;"><strong>Signed On:</strong> ' . htmlspecialchars($contract['signed_at'] ?? 'NOT FINALIZED') . '</p>
    </div>

    <div class="footer">
        This digital document is a legally binding agreement under the Electronic Transactions Act. <br>
        Generated securely via Diversity.is Platform
    </div>
</body>
</html>';

        if (class_exists('\\Dompdf\\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf();
            $options = new \Dompdf\Options();
            $options->set('defaultFont', 'Poppins');
            $options->set('isRemoteEnabled', true);
            $dompdf->setOptions($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfContent = $dompdf->output();
        } else {
            // Fallback if DomPDF goes missing
            $lines = ["PARTIES:", "CLIENT: $clientName", "FREELANCER: $freelancerName", "----", "TERMS", $contract['terms'] ?? ''];
            $pdfContent = $this->generateMinimalPDF("SERVICE AGREEMENT: " . $title, $lines);
        }
        
        if (file_put_contents($pdfPath, $pdfContent) === false) {
            throw new RuntimeException('Failed to write PDF file.');
        }

        return $pdfPath;
    }

    public function sendContractEmail(int $contractId, string $recipientEmail, string $senderName): bool
    {
        $pdfPath = $this->generateProfessionalPDF($contractId);
        if (!is_file($pdfPath)) {
            throw new RuntimeException('Unable to generate PDF for email.');
        }

        $pdfContent = file_get_contents($pdfPath);
        $pdfBase64 = chunk_split(base64_encode($pdfContent));
        $boundary = md5((string)time());

        $subject = 'Your Contract Document - #' . $contractId;
        $htmlBody = '<p>Hello,</p><p>' . htmlspecialchars($senderName) . ' has sent you a contract document.</p><p>Please find the PDF attached to this email.</p><p>Best regards,<br>Diversity.is</p>';

        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $htmlBody . "\r\n";
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: application/pdf; name=\"Contract-{$contractId}.pdf\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"Contract-{$contractId}.pdf\"\r\n\r\n";
        $message .= $pdfBase64 . "\r\n";
        $message .= "--{$boundary}--";

        $this->sendRawSmtpMail($recipientEmail, '', $subject, $message, $boundary);
        
        @unlink($pdfPath);
        return true;
    }

    private function sendRawSmtpMail(string $toEmail, string $toName, string $subject, string $multipartBody, string $boundary): bool
    {
        $host = trim((string) config::get('SMTP_HOST', '127.0.0.1'));
        $port = (int) config::get('SMTP_PORT', 1025);
        $username = trim((string) config::get('SMTP_USERNAME', ''));
        $password = trim((string) config::get('SMTP_PASSWORD', ''));
        $fromEmail = trim((string) config::get('SMTP_FROM_EMAIL', 'no-reply@diversity.is'));
        $fromName = trim((string) config::get('SMTP_FROM_NAME', 'Diversity Contracts'));

        if ($host === '' || $port <= 0 || $fromEmail === '') {
            throw new RuntimeException('SMTP configuration is incomplete.');
        }

        $socket = @stream_socket_client($host . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            throw new RuntimeException('SMTP connection failed: ' . $errstr);
        }

        try {
            stream_set_timeout($socket, 10);
            $this->smtpCommand($socket, null, 220);
            $this->smtpCommand($socket, 'EHLO localhost', 250);
            
            // Google SMTP strictly requires STARTTLS before AUTH
            // For general stability, if port is 587 (or anything not local 1025) and there's a username, trigger TLS
            if ($username !== '' || $port === 587) {
                $this->smtpCommand($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Unable to enable TLS for SMTP.');
                }
                // Re-EHLO after TLS negotiation
                $this->smtpCommand($socket, 'EHLO localhost', 250);
            }
            
            // MailHog typically doesn't need AUTH, but if username is set:
            if ($username !== '') {
                $this->smtpCommand($socket, 'AUTH LOGIN', 334);
                $this->smtpCommand($socket, base64_encode($username), 334);
                $this->smtpCommand($socket, base64_encode($password), 235);
            }

            $this->smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', 250);
            $this->smtpCommand($socket, 'RCPT TO:<' . $toEmail . '>', 250);
            $this->smtpCommand($socket, 'DATA', 354);

            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $fromEmail),
                'To: ' . sprintf('"%s" <%s>', addslashes($toName !== '' ? $toName : $toEmail), $toEmail),
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'MIME-Version: 1.0',
                'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
            ];

            $mailData = implode("\r\n", $headers) . "\r\n\r\n" . $multipartBody . "\r\n.";
            $this->smtpCommand($socket, $mailData, 250);
            $this->smtpCommand($socket, 'QUIT', 221);
        } finally {
            fclose($socket);
        }

        return true;
    }

    private function readSmtpLine($socket): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^\d{3}\s/', $line)) {
                break;
            }
        }
        return $response;
    }

    private function smtpCommand($socket, ?string $command, int $expectedCode): string
    {
        if ($command !== null) {
            fwrite($socket, $command . "\r\n");
        }
        $response = $this->readSmtpLine($socket);
        if ($response !== '' && (int) substr($response, 0, 3) !== $expectedCode) {
            // For robust failure handling, if error occurs we just log and throw.
            throw new RuntimeException('SMTP error: ' . trim($response));
        }
        return $response;
    }

    /**
     * Generates a professional-grade PDF 1.4 file with proper legal document formatting.
     * Includes proper typography, spacing, headers, and professional layout.
     */
    private function generateMinimalPDF(string $header, array $lines): string
    {
        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj <</Type/Catalog/Pages 2 0 R>> endobj\n";
        $pdf .= "2 0 obj <</Type/Pages/Kids[3 0 R]/Count 1>> endobj\n";
        $pdf .= "3 0 obj <</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Resources<</Font<</F1 4 0 R/F2 5 0 R/F3 6 0 R>>>>/Contents 7 0 R>> endobj\n";
        $pdf .= "4 0 obj <</Type/Font/Subtype/Type1/BaseFont/Helvetica-Bold>> endobj\n";
        $pdf .= "5 0 obj <</Type/Font/Subtype/Type1/BaseFont/Helvetica>> endobj\n";
        $pdf .= "6 0 obj <</Type/Font/Subtype/Type1/BaseFont/Helvetica-Oblique>> endobj\n";
        
        $content = "BT\n";
        
        // Header - Professional branding
        $content .= "/F1 16 Tf 50 800 Td (DIVERSITY.IS) Tj\n";
        $content .= "0 -10 Td /F2 9 Tf (OFFICIAL LEGAL DOCUMENTATION) Tj\n";
        $content .= "0 -15 Td /F3 7 Tf (Electronically generated on " . date('F j, Y, H:i') . ") Tj\n";
        $content .= "0 -20 Td\n";
        
        // Horizontal line separator
        $content .= "ET\nq\n0.7 w\n50 750 m\n545 750 l\nS\nQ\nBT\n";
        
        // Main content
        $content .= "0 -35 Td /F1 13 Tf (" . addslashes(strtoupper($header)) . ") Tj\n";
        $content .= "0 -25 Td\n";
        
        $y = 680;
        $maxY = 100;
        
        foreach ($lines as $line) {
            if ($y < $maxY + 50) {
                break;
            }
            
            $line = trim((string)$line);
            if ($line === '') {
                $content .= "0 -10 Td\n";
                $y -= 10;
                continue;
            }
            
            // Clean non-ASCII for this minimal generator to avoid corruption
            if (function_exists('iconv')) {
                $line = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $line);
            }
            $line = preg_replace('/[^\x20-\x7E]/', '', $line);
            
            // Detect section headers
            $isSection = (strpos($line, 'SECTION:') === 0 || 
                         strpos($line, 'PARTIES:') === 0 || 
                         strpos($line, 'PARTIES ') === 0 ||
                         strpos($line, '----') !== false ||
                         strpos($line, '---') !== false);
            
            $isBold = $isSection || (strlen($line) < 60 && ctype_upper($line));
            $font = $isBold ? "/F1" : "/F2";
            $fontSize = $isSection ? "11" : "10";
            
            $content .= "$font $fontSize Tf\n";
            $wrapped = wordwrap($line, 85, "\n");
            
            foreach (explode("\n", $wrapped) as $l) {
                $l = trim($l);
                if ($l === '') continue;
                if ($y < $maxY) break 2;
                
                $content .= "(" . addslashes($l) . ") Tj\n";
                $content .= "0 -14 Td\n";
                $y -= 14;
            }
        }
        
        // Footer branding
        $content .= "0 -" . ($y - 60) . " Td /F3 8 Tf (DOCUMENT VALIDATION) Tj\n";
        $content .= "0 -12 Td /F2 8 Tf (Verified legal instrument generated by Diversity.is SaaS Platform) Tj\n";
        $content .= "0 -10 Td (Ref: " . strtoupper(substr(md5($header . time() . rand()), 0, 16)) . ") Tj\n";
        $content .= "0 -8 Td (Contact: legal@diversity.is | System Date: " . date('Y-m-d') . ") Tj\n";
        $content .= "ET";
        
        $pdf .= "7 0 obj <</Length " . strlen($content) . ">> stream\n" . $content . "\nendstream\nendobj\n";
        $pdf .= "xref\n0 8\n0000000000 65535 f\n";
        $pdf .= "trailer <</Size 8/Root 1 0 R>>\n";
        $pdf .= "startxref\n" . (strlen($pdf) - 7) . "\n%%EOF";
        
        return $pdf;
    }

    public function latestAnalysisByContractId(int $contractId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT risk_score, analysis_json FROM contracts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $contractId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['analysis_json'])) {
            $analysis = json_decode($row['analysis_json'], true);
            if (is_array($analysis)) {
                // Ensure proper structure (normalize if needed)
                return $this->openRouterService->normalizeAnalysisOutput($analysis);
            }
        }
        return null;
    }

    private function contractHasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnCache)) {
            return $this->columnCache[$column];
        }

        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM contracts LIKE " . $this->pdo->quote($column));
            $this->columnCache[$column] = (bool) ($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
        } catch (Throwable $exception) {
            $this->columnCache[$column] = false;
        }

        return $this->columnCache[$column];
    }

    private function ensureColumn(string $column, string $definition): void
    {
        // Schema is owned by Diversity.sql (single source of truth).
        return;
    }

    private function ensureContractSchema(): void
    {
        // Schema is owned by Diversity.sql (single source of truth).
        return;
    }

    private function sanitizeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? $value);
    }

    private function startsWithUppercase(string $value): bool
    {
        return (bool) preg_match('/^\p{Lu}/u', $value);
    }

    private function normalizeSignature(string $signature): string
    {
        $signature = trim($signature);
        if ($signature === '') {
            throw new RuntimeException('Signature is required.');
        }

        if (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=\s]+$#', $signature)) {
            throw new RuntimeException('Invalid signature format.');
        }

        return preg_replace('/\s+/', '', $signature) ?? $signature;
    }

    private function normalizeContractPayload(array $payload): array
    {
        $title = $this->sanitizeText((string) ($payload['title'] ?? ''));
        $description = $this->sanitizeText((string) ($payload['description'] ?? ''));
        $terms = $this->sanitizeText((string) ($payload['terms'] ?? ''));
        $paymentDetails = $this->sanitizeText((string) ($payload['payment_details'] ?? ''));
        $amountRaw = trim((string) ($payload['amount'] ?? ''));
        $amount = is_numeric($amountRaw) ? round((float) $amountRaw, 2) : null;

        if ($title === '') {
            $title = 'Contract';
        }
        if ($terms === '') {
            throw new RuntimeException('Contract terms are required.');
        }
        if (mb_strlen($terms) < 20 || mb_strlen($terms) > 4000) {
            throw new RuntimeException('Contract terms must be between 20 and 4000 characters.');
        }
        if (!$this->startsWithUppercase($terms)) {
            throw new RuntimeException('Contract terms must start with an uppercase letter.');
        }
        if ($paymentDetails === '') {
            throw new RuntimeException('Payment details are required.');
        }
        if (mb_strlen($paymentDetails) < 5 || mb_strlen($paymentDetails) > 2000) {
            throw new RuntimeException('Payment details must be between 5 and 2000 characters.');
        }
        if ($amount === null || $amount <= 0) {
            throw new RuntimeException('Amount must be greater than 0.');
        }
        if ($amount > 10000000) {
            throw new RuntimeException('Amount is too high.');
        }

        $startsAtRaw = trim((string) ($payload['starts_at'] ?? ''));
        $endsAtRaw = trim((string) ($payload['ends_at'] ?? ''));
        $deadlineAtRaw = trim((string) ($payload['deadline_at'] ?? ''));
        $startsAt = $this->parseDateTimeLocal($startsAtRaw);
        $endsAt = $this->parseDateTimeLocal($endsAtRaw);
        $deadlineAt = $this->parseDateTimeLocal($deadlineAtRaw);

        if ($startsAtRaw !== '' && $startsAt === null) {
            throw new RuntimeException('Start date is invalid.');
        }
        if ($endsAtRaw !== '' && $endsAt === null) {
            throw new RuntimeException('End date is invalid.');
        }
        if ($deadlineAtRaw !== '' && $deadlineAt === null) {
            throw new RuntimeException('Deadline is invalid.');
        }
        if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) <= strtotime($startsAt)) {
            throw new RuntimeException('End date must be after the start date.');
        }

        return [
            'title' => $title,
            'description' => $description,
            'terms' => $terms,
            'payment_details' => $paymentDetails,
            'amount' => $amount,
            'deadline_at' => $deadlineAt,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_by_client_id' => (int) ($payload['created_by_client_id'] ?? 0),
        ];
    }

    private function fetchContractRowForUpdate(int $contractId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contracts WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $contractId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function withDerivedStatus(array $row): array
    {
        $row['status'] = $this->derivedStatus($row);
        return $row;
    }

    private function derivedStatus(array $row): string
    {
        $state = $this->workflowState($row);
        if ($state === 'completed') {
            return 'finalized';
        }
        if ($state === 'refused') {
            return 'cancelled';
        }
        if ($state === 'waiting_freelancer') {
            return 'waiting';
        }
        return 'draft';
    }

    public function workflowState(array $row): string
    {
        $clientSigned = (int) ($row['client_signed'] ?? 0) === 1;
        $freelancerSigned = (int) ($row['freelancer_signed'] ?? 0) === 1;
        $freelancerRefusedAt = trim((string) ($row['freelancer_refused_at'] ?? ''));

        if ($clientSigned && $freelancerSigned) {
            return 'completed';
        }

        if ($freelancerRefusedAt !== '') {
            return 'refused';
        }

        if ($clientSigned) {
            return 'waiting_freelancer';
        }

        return 'waiting_client';
    }

    public function listUsers(): array
    {
        try {
            return $this->pdo->query('SELECT * FROM users ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            return [];
        }
    }

    public function userLabel(array $row): string
    {
        $full = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        if ($full !== '') {
            return $full;
        }

        return trim((string) ($row['email'] ?? '')) ?: ('User #' . (int) ($row['id'] ?? 0));
    }

    public function parseDateTimeLocal(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value);

        return $date instanceof DateTimeInterface ? $date->format('Y-m-d H:i:s') : null;
    }

    public function listOffers(): array
    {
        return $this->pdo->query('SELECT id, title FROM job_offers ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    private function enrichRowsWithRules(array $rows): array
    {
        $rulesByContract = $this->ruleController->listGroupedByContractIds(array_column($rows, 'id'));

        foreach ($rows as &$row) {
            $contractId = (int) ($row['id'] ?? 0);
            $rules = $rulesByContract[$contractId] ?? [];
            $firstRule = $rules[0] ?? [];

            $row['rules'] = $rules;
            $row['rules_count'] = count($rules);
            $row['rules_terms'] = '';
            $row['rules_deadline'] = '';
            $row['rules_payment_terms'] = '';
            $row['rules_penalties'] = '';

            foreach ($rules as $rule) {
                $ruleType = strtolower((string) ($rule['rule_type'] ?? ''));
                $description = (string) ($rule['description'] ?? '');
                $dueDate = (string) ($rule['due_date'] ?? '');
                $penalty = (string) ($rule['penalty'] ?? '');

                if ($row['rules_terms'] === '' && in_array($ruleType, ['scope', 'delivery', 'terms'], true) && trim($description) !== '') {
                    $row['rules_terms'] = $description;
                }
                if ($row['rules_deadline'] === '' && $ruleType === 'deadline' && trim($dueDate) !== '') {
                    $row['rules_deadline'] = $dueDate;
                }
                if ($row['rules_payment_terms'] === '' && $ruleType === 'payment' && trim($description) !== '') {
                    $row['rules_payment_terms'] = $description;
                }
                if ($row['rules_penalties'] === '' && in_array($ruleType, ['penalties', 'legal', 'penalty'], true) && trim($penalty) !== '') {
                    $row['rules_penalties'] = $penalty;
                }
            }

            if ($row['rules_terms'] === '') {
                $row['rules_terms'] = (string) ($firstRule['description'] ?? '');
            }
            if ($row['rules_deadline'] === '') {
                $row['rules_deadline'] = (string) ($firstRule['due_date'] ?? '');
            }
            if ($row['rules_penalties'] === '') {
                $row['rules_penalties'] = (string) ($firstRule['penalty'] ?? '');
            }
            if ($row['rules_payment_terms'] === '' && ($firstRule['rule_type'] ?? '') === 'payment') {
                $row['rules_payment_terms'] = (string) ($firstRule['description'] ?? '');
            }
        }
        unset($row);

        return $rows;
    }

    public function listBackofficeRows(): array
    {
        $sql = 'SELECT c.*,
                       COALESCE(c.title, o.title, CONCAT("Contract #", c.id)) AS contract_title,
                       o.title AS offer_title,
                       cu.first_name AS client_first, cu.last_name AS client_last,
                       fu.first_name AS freelancer_first, fu.last_name AS freelancer_last
                FROM contracts c
                LEFT JOIN job_offers o ON o.id = c.job_offer_id
                LEFT JOIN users cu ON cu.id = c.client_id
                LEFT JOIN users fu ON fu.id = c.freelancer_id
                ORDER BY c.created_at DESC';

        $rows = $this->enrichRowsWithRules($this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
        return array_map(fn(array $row): array => $this->withDerivedStatus($row), $rows);
    }

    public function buildBackofficeStats(array $rows): array
    {
        $active = 0;
        $draft = 0;

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? $this->derivedStatus($row));
            if ($status === 'waiting') {
                $active++;
            }
            if ($status === 'draft') {
                $draft++;
            }
        }

        return [
            'total' => count($rows),
            'active' => $active,
            'draft' => $draft,
        ];
    }

    public function listUserContracts(int $userId): array
    {
        $sql = 'SELECT c.*,
                       COALESCE(c.title, o.title, CONCAT("Contract #", c.id)) AS contract_title,
                       o.title AS offer_title,
                       o.description AS offer_description,
                       cu.first_name AS client_first, cu.last_name AS client_last,
                       fu.first_name AS freelancer_first, fu.last_name AS freelancer_last
                FROM contracts c
                LEFT JOIN job_offers o ON o.id = c.job_offer_id
                INNER JOIN users cu ON cu.id = c.client_id
                INNER JOIN users fu ON fu.id = c.freelancer_id
                WHERE c.client_id = :uid OR c.freelancer_id = :uid
                ORDER BY c.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        $rows = $this->enrichRowsWithRules($stmt->fetchAll(PDO::FETCH_ASSOC));
        return array_map(fn(array $row): array => $this->withDerivedStatus($row), $rows);
    }

    public function listClientAcceptedApplicationsWithoutContract(int $clientId): array
    {
        $sql = 'SELECT a.job_offer_id, a.freelancer_id,
                       o.title AS offer_title,
                       o.description AS offer_description,
                       o.budget,
                       o.deadline_at,
                       u.first_name, u.last_name, u.email
                FROM job_offer_applications a
                INNER JOIN job_offers o ON o.id = a.job_offer_id
                INNER JOIN users u ON u.id = a.freelancer_id
                LEFT JOIN contracts c ON c.job_offer_id = a.job_offer_id AND c.freelancer_id = a.freelancer_id
                WHERE o.client_id = :client_id
                  AND a.status = "accepted"
                  AND c.id IS NULL
                ORDER BY a.decided_at DESC, a.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['client_id' => $clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function canClientCreateContractForPair(int $clientId, int $offerId, int $freelancerId): bool
    {
        $sql = 'SELECT a.id
                FROM job_offer_applications a
                INNER JOIN job_offers o ON o.id = a.job_offer_id
                LEFT JOIN contracts c ON c.job_offer_id = a.job_offer_id AND c.freelancer_id = a.freelancer_id
                WHERE a.job_offer_id = :offer_id
                  AND a.freelancer_id = :freelancer_id
                  AND a.status = "accepted"
                  AND o.client_id = :client_id
                  AND c.id IS NULL
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'offer_id' => $offerId,
            'freelancer_id' => $freelancerId,
            'client_id' => $clientId,
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAcceptedApplicationPrefill(int $clientId, int $offerId, int $freelancerId): ?array
    {
        $sql = 'SELECT a.job_offer_id, a.freelancer_id, a.cover_letter, a.applied_at,
                       o.title AS offer_title, o.description AS offer_description, o.budget, o.deadline_at,
                       u.first_name, u.last_name, u.email
                FROM job_offer_applications a
                INNER JOIN job_offers o ON o.id = a.job_offer_id
                INNER JOIN users u ON u.id = a.freelancer_id
                LEFT JOIN contracts c ON c.job_offer_id = a.job_offer_id AND c.freelancer_id = a.freelancer_id
                WHERE o.client_id = :client_id
                  AND a.job_offer_id = :offer_id
                  AND a.freelancer_id = :freelancer_id
                  AND a.status = "accepted"
                  AND c.id IS NULL
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'client_id' => $clientId,
            'offer_id' => $offerId,
            'freelancer_id' => $freelancerId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createFromAcceptedApplication(int $clientId, int $offerId, int $freelancerId, array $payload): int
    {
        $this->pdo->beginTransaction();

        try {
            $sql = 'SELECT a.id,
                           o.client_id,
                           o.title AS offer_title,
                           o.description AS offer_description
                    FROM job_offer_applications a
                    INNER JOIN job_offers o ON o.id = a.job_offer_id
                    LEFT JOIN contracts c ON c.job_offer_id = a.job_offer_id AND c.freelancer_id = a.freelancer_id
                    WHERE a.job_offer_id = :offer_id
                      AND a.freelancer_id = :freelancer_id
                      AND a.status = "accepted"
                      AND o.client_id = :client_id
                      AND c.id IS NULL
                    LIMIT 1
                    FOR UPDATE';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'offer_id' => $offerId,
                'freelancer_id' => $freelancerId,
                'client_id' => $clientId,
            ]);
            $pair = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pair) {
                throw new RuntimeException('This accepted application is no longer available for contract creation.');
            }

            $contractData = $this->normalizeContractPayload($payload + ['created_by_client_id' => $clientId]);
            if ($contractData['title'] === 'Contract') {
                $contractData['title'] = 'Contract for ' . trim((string) ($pair['offer_title'] ?? 'this project'));
            }
            if ($contractData['description'] === '') {
                $contractData['description'] = trim((string) ($pair['offer_description'] ?? ''));
            }

            $insert = $this->pdo->prepare('INSERT INTO contracts (
                    job_offer_id, freelancer_id, client_id, title, description, terms, amount,
                    payment_details, deadline_at, starts_at, ends_at, created_by_client_id,
                    client_signed, freelancer_signed, client_signature, freelancer_signature,
                    signed_at, created_at, updated_at
                ) VALUES (
                    :job_offer_id, :freelancer_id, :client_id, :title, :description, :terms, :amount,
                    :payment_details, :deadline_at, :starts_at, :ends_at, :created_by_client_id,
                    0, 0, NULL, NULL,
                    NULL, NOW(), NOW()
                )');

            $insert->execute([
                'job_offer_id' => $offerId,
                'freelancer_id' => $freelancerId,
                'client_id' => $clientId,
                'title' => $contractData['title'],
                'description' => $contractData['description'] !== '' ? $contractData['description'] : null,
                'terms' => $contractData['terms'],
                'amount' => $contractData['amount'],
                'payment_details' => $contractData['payment_details'],
                'deadline_at' => $contractData['deadline_at'],
                'starts_at' => $contractData['starts_at'],
                'ends_at' => $contractData['ends_at'],
                'created_by_client_id' => $clientId,
            ]);

            $contractId = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
            return $contractId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function create(array $payload): int
    {
        $offerId = (int) ($payload['job_offer_id'] ?? 0);
        $clientId = (int) ($payload['client_id'] ?? 0);
        $freelancerId = (int) ($payload['freelancer_id'] ?? 0);

        if ($offerId <= 0 || $clientId <= 0 || $freelancerId <= 0) {
            throw new RuntimeException('Offer, client, and freelancer are required.');
        }

        return $this->createFromAcceptedApplication($clientId, $offerId, $freelancerId, [
            'title' => (string) ($payload['title'] ?? ''),
            'description' => (string) ($payload['description'] ?? ''),
            'terms' => (string) ($payload['terms'] ?? ''),
            'amount' => (string) ($payload['amount'] ?? ''),
            'payment_details' => trim((string) ($payload['payment_details'] ?? '')) !== ''
                ? (string) ($payload['payment_details'] ?? '')
                : ('Payment amount: ' . trim((string) ($payload['amount'] ?? '0')) . ' TND'),
            'deadline_at' => (string) ($payload['deadline_at'] ?? ''),
            'starts_at' => (string) ($payload['starts_at'] ?? ''),
            'ends_at' => (string) ($payload['ends_at'] ?? ''),
        ]);
    }

    public function updateStatus(int $contractId, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE contracts SET updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $contractId]);
        return $stmt->rowCount() > 0;
    }

    public function updateByClient(int $contractId, int $clientId, array $payload): bool
    {
        $this->pdo->beginTransaction();

        try {
            $contract = $this->fetchContractRowForUpdate($contractId);
            if (!$contract || (int) ($contract['client_id'] ?? 0) !== $clientId) {
                return false;
            }
            if ((int) ($contract['client_signed'] ?? 0) === 1 || (int) ($contract['freelancer_signed'] ?? 0) === 1) {
                return false;
            }

            $clean = $this->normalizeContractPayload($payload + [
                'title' => (string) ($contract['title'] ?? ''),
                'description' => (string) ($contract['description'] ?? ''),
                'created_by_client_id' => (int) ($contract['created_by_client_id'] ?? $clientId),
            ]);

            $stmt = $this->pdo->prepare('UPDATE contracts
                SET terms = :terms,
                    amount = :amount,
                    payment_details = :payment_details,
                    starts_at = :starts_at,
                    ends_at = :ends_at,
                    deadline_at = :deadline_at,
                    updated_at = NOW()
                WHERE id = :id
                  AND client_id = :client_id
                  AND client_signed = 0
                  AND freelancer_signed = 0');
            $stmt->execute([
                'terms' => $clean['terms'],
                'amount' => $clean['amount'],
                'payment_details' => $clean['payment_details'],
                'starts_at' => $clean['starts_at'],
                'ends_at' => $clean['ends_at'],
                'deadline_at' => $clean['deadline_at'],
                'id' => $contractId,
                'client_id' => $clientId,
            ]);

            $this->pdo->commit();
            return $stmt->rowCount() > 0;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function saveRulesByClient(int $contractId, int $clientId, array $payload): int
    {
        $this->pdo->beginTransaction();

        try {
            $contract = $this->fetchContractRowForUpdate($contractId);
            if (!$contract || (int) ($contract['client_id'] ?? 0) !== $clientId) {
                throw new RuntimeException('You can only manage rules for your own contracts.');
            }
            if ((int) ($contract['client_signed'] ?? 0) === 1 || (int) ($contract['freelancer_signed'] ?? 0) === 1) {
                throw new RuntimeException('Rules can no longer be changed after signing starts.');
            }

            $rulesCount = $this->ruleController->replaceForContract($contractId, $payload);

            $this->pdo->prepare('UPDATE contracts SET updated_at = NOW() WHERE id = :id')->execute(['id' => $contractId]);
            $this->pdo->commit();
            return $rulesCount;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function signByClient(int $contractId, int $clientId, string $signature): bool
    {
        $signature = $this->normalizeSignature($signature);
        $this->pdo->beginTransaction();

        try {
            $contract = $this->fetchContractRowForUpdate($contractId);
            if (!$contract || (int) ($contract['client_id'] ?? 0) !== $clientId) {
                throw new RuntimeException('You cannot sign this contract.');
            }
            if ((int) ($contract['client_signed'] ?? 0) === 1) {
                throw new RuntimeException('This contract has already been signed by the client.');
            }
            if ((int) ($contract['freelancer_signed'] ?? 0) === 1) {
                throw new RuntimeException('This contract has already been finalized.');
            }
            if (trim((string) ($contract['freelancer_refused_at'] ?? '')) !== '') {
                throw new RuntimeException('This contract has already been refused.');
            }
            if ($this->ruleController->countByContractId($contractId) === 0) {
                throw new RuntimeException('Add the contract rules before signing.');
            }

            $update = $this->pdo->prepare('UPDATE contracts
                SET client_signed = 1,
                    client_signature = :signature,
                    updated_at = NOW()
                WHERE id = :id');
            $update->execute([
                'signature' => $signature,
                'id' => $contractId,
            ]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function signByFreelancer(int $contractId, int $freelancerId, string $signature = ''): bool
    {
        $signature = $this->normalizeSignature($signature);
        $this->pdo->beginTransaction();

        try {
            $contract = $this->fetchContractRowForUpdate($contractId);
            if (!$contract || (int) ($contract['freelancer_id'] ?? 0) !== $freelancerId) {
                throw new RuntimeException('You cannot sign this contract.');
            }
            if ((int) ($contract['client_signed'] ?? 0) !== 1) {
                throw new RuntimeException('The client must sign first.');
            }
            if ((int) ($contract['freelancer_signed'] ?? 0) === 1) {
                throw new RuntimeException('This contract has already been signed.');
            }
            if (trim((string) ($contract['freelancer_refused_at'] ?? '')) !== '') {
                throw new RuntimeException('This contract has been refused and cannot be signed.');
            }
            if ($this->ruleController->countByContractId($contractId) === 0) {
                throw new RuntimeException('The contract rules must be completed before signing.');
            }

            $update = $this->pdo->prepare('UPDATE contracts
                SET freelancer_signed = 1,
                    freelancer_signature = :signature,
                    signed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id');
            $update->execute([
                'signature' => $signature,
                'id' => $contractId,
            ]);

            if ((int) ($contract['job_offer_id'] ?? 0) > 0) {
                $offerId = (int) $contract['job_offer_id'];
                $this->pdo->prepare('UPDATE job_offers SET status = :status, updated_at = NOW() WHERE id = :offer_id')
                    ->execute([
                        'status' => 'closed',
                        'offer_id' => $offerId,
                    ]);

                $this->pdo->prepare('UPDATE job_offer_applications
                    SET status = :status, decided_at = NOW(), updated_at = NOW()
                    WHERE job_offer_id = :offer_id
                      AND freelancer_id <> :freelancer_id
                      AND status = :pending')
                    ->execute([
                        'status' => 'rejected',
                        'offer_id' => $offerId,
                        'freelancer_id' => $freelancerId,
                        'pending' => 'pending',
                    ]);
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function refuseByFreelancer(int $contractId, int $freelancerId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE contracts
            SET freelancer_refused_at = NOW(), updated_at = NOW()
            WHERE id = :id
              AND freelancer_id = :freelancer_id
              AND client_signed = 1
              AND freelancer_signed = 0
              AND freelancer_refused_at IS NULL');

        $stmt->execute([
            'id' => $contractId,
            'freelancer_id' => $freelancerId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deleteById(int $contractId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM contracts WHERE id = :id');
        $stmt->execute(['id' => $contractId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByClient(int $contractId, int $clientId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM contracts
            WHERE id = :id
              AND client_id = :client_id
              AND client_signed = 0
              AND freelancer_signed = 0');

        $stmt->execute([
            'id' => $contractId,
            'client_id' => $clientId,
        ]);

        return $stmt->rowCount() > 0;
    }
}
