<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/EventController.php');

if (!UserController::isAuthenticated()) { header('Location: auth.php'); exit; }

$eventController = new EventController();
$eventId = $_GET['event_id'] ?? 0;
$event = $eventController->getEventById($eventId);

if (!$event) { header('Location: events.php'); exit; }

$sidebarUser = UserController::currentUser() ?? [];
$startDate = $event->getStartDate();
$endDate = $event->getEndDate();

// Calculate number of days
$start = new DateTime($startDate);
$end = new DateTime($endDate);
$interval = $start->diff($end);
$numDays = $interval->days + 1; // inclusive
if ($numDays < 1) $numDays = 1;

// Build days array for JS
$daysArray = [];
for ($i = 0; $i < $numDays; $i++) {
    $d = clone $start;
    $d->modify("+$i days");
    $daysArray[] = [
        'dayNum' => $i + 1,
        'date' => $d->format('Y-m-d'),
        'label' => $d->format('l, M j')
    ];
}
$daysJson = json_encode($daysArray);

// Fetch existing resources
include_once(__DIR__ . '/../../Controllers/ResourceController.php');
$resourceController = new ResourceController();
$existingResources = $resourceController->getResourcesByEvent($eventId);

$initialData = [
    'planning' => null,
    'rules' => null,
    'materials' => null
];

foreach ($existingResources as $res) {
    if ($res['type'] === 'planning') {
        $initialData['planning'] = [
            'id' => $res['id'],
            'title' => $res['title'],
            'description' => $res['description']
        ];
    } elseif ($res['type'] === 'regles') {
        $initialData['rules'] = [
            'id' => $res['id'],
            'title' => $res['title'],
            'description' => $res['description']
        ];
    } elseif ($res['type'] === 'materiel') {
        $initialData['materials'] = [
            'id' => $res['id'],
            'title' => $res['title'],
            'description' => $res['description']
        ];
    }
}
$initialDataJson = json_encode($initialData);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configure Resources — <?= htmlspecialchars($event->getTitle()) ?></title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/projects.css">
    <link rel="stylesheet" href="../../assets/css/event-wizard.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg">
    <canvas id="gradient-canvas"></canvas>

    <nav class="navbar">
        <div class="container">
            <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
        </div>
    </nav>

    <main class="profile-main">
        <div class="wizard-container">

            <!-- Wizard Steps -->
            <div class="wizard-header">
                <div class="wizard-steps">
                    <div class="wizard-step completed">
                        <span class="wizard-step-num">✓</span>
                        <span>Create Event</span>
                    </div>
                    <div class="wizard-step-connector done"></div>
                    <div class="wizard-step active">
                        <span class="wizard-step-num">2</span>
                        <span>Configure Resources</span>
                    </div>
                </div>
            </div>

            <!-- Event Summary -->
            <div class="event-summary-card">
                <div class="event-summary-icon">
                    <i data-lucide="calendar-check"></i>
                </div>
                <div class="event-summary-info">
                    <h3><?= htmlspecialchars($event->getTitle()) ?></h3>
                    <p><?= date('M j', strtotime($startDate)) ?> — <?= date('M j, Y', strtotime($endDate)) ?> • <?= htmlspecialchars($event->getLocation()) ?></p>
                </div>
            </div>

            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash_success'])): ?>
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; padding: 14px; border-radius: 12px; margin-bottom: 25px; font-size: 0.85rem;">
                    <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
                </div>
            <?php endif; ?>

            <!-- Resource Type Cards -->
            <h2 class="text-h2" style="margin-bottom: 8px;">Choose Resource Types</h2>
            <p class="text-body" style="opacity: 0.5; margin-bottom: 25px;">Click each card to configure that resource for your event.</p>

            <div class="resource-cards-grid">
                <!-- Planning -->
                <div class="resource-type-card" id="card-planning" onclick="openModal('planning')" style="--card-accent: #6366f1;">
                    <span class="card-check">✓</span>
                    <div class="card-icon planning">📅</div>
                    <h4>Planning</h4>
                    <p>Schedule activities day by day with time slots</p>
                </div>
                <!-- Rules -->
                <div class="resource-type-card" id="card-rules" onclick="openModal('rules')" style="--card-accent: #f59e0b;">
                    <span class="card-check">✓</span>
                    <div class="card-icon rules">📜</div>
                    <h4>Rules</h4>
                    <p>Define numbered guidelines for participants</p>
                </div>
                <!-- Materials -->
                <div class="resource-type-card" id="card-materials" onclick="openModal('materials')" style="--card-accent: #10b981;">
                    <span class="card-check">✓</span>
                    <div class="card-icon materials">🔧</div>
                    <h4>Materials</h4>
                    <p>Select equipment and supplies needed</p>
                </div>
            </div>

            <!-- Drop Zone -->
            <h2 class="text-h2" style="margin-bottom: 8px;">Configured Resources</h2>
            <p class="text-body" style="opacity: 0.5; margin-bottom: 15px;">Resources you configure will appear here.</p>

            <div class="drop-zone" id="dropZone">
                <div class="drop-zone-placeholder" id="dropPlaceholder">
                    <span style="font-size: 32px; display: block; margin-bottom: 8px;">📦</span>
                    <p>No resources configured yet.<br>Click the cards above to get started.</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="wizard-actions">
                <a href="events.php" class="btn-skip">Skip & Finish Later</a>
                <button class="btn-confirm-resources" id="btnConfirm" disabled onclick="submitResources()">
                    <i data-lucide="check-circle"></i> Confirm Resources
                </button>
            </div>
        </div>
    </main>

    <!-- ════════════════════════════════════════════════════════ -->
    <!-- PLANNING MODAL                                          -->
    <!-- ════════════════════════════════════════════════════════ -->
    <div class="wiz-modal-overlay" id="modal-planning">
        <div class="wiz-modal">
            <div class="wiz-modal-header">
                <h3>📅 Planning — Day-by-Day Schedule</h3>
                <button class="wiz-modal-close" onclick="closeModal('planning')">✕</button>
            </div>
            <div class="wiz-modal-body">
                <!-- Date range display -->
                <div class="planning-date-range">
                    <div>
                        <div class="date-label">Start</div>
                        <div class="date-value"><?= date('M j, Y — H:i', strtotime($startDate)) ?></div>
                    </div>
                    <span class="date-sep">→</span>
                    <div>
                        <div class="date-label">End</div>
                        <div class="date-value"><?= date('M j, Y — H:i', strtotime($endDate)) ?></div>
                    </div>
                    <div style="margin-left: auto; text-align: right;">
                        <div class="date-label">Duration</div>
                        <div class="date-value"><?= $numDays ?> day<?= $numDays > 1 ? 's' : '' ?></div>
                    </div>
                </div>

                <!-- Day pagination -->
                <div class="day-pagination">
                    <button class="day-nav-btn" id="prevDayBtn" onclick="navDay(-1)">‹</button>
                    <div class="day-indicator">
                        <p class="day-label" id="dayLabel">Day 1</p>
                        <p class="day-date" id="dayDate"></p>
                    </div>
                    <button class="day-nav-btn" id="nextDayBtn" onclick="navDay(1)">›</button>
                </div>

                <!-- Activity slots container -->
                <div id="slotsContainer"></div>

                <button class="add-activity-btn" onclick="addSlot()">
                    <span>＋</span> Add Activity
                </button>
            </div>
            <div class="wiz-modal-footer">
                <button class="wiz-btn wiz-btn-ghost" onclick="closeModal('planning')">Cancel</button>
                <button class="wiz-btn wiz-btn-primary" onclick="savePlanning()">Save Planning</button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════ -->
    <!-- RULES MODAL                                             -->
    <!-- ════════════════════════════════════════════════════════ -->
    <div class="wiz-modal-overlay" id="modal-rules">
        <div class="wiz-modal">
            <div class="wiz-modal-header">
                <h3>📜 Rules — Event Guidelines</h3>
                <button class="wiz-modal-close" onclick="closeModal('rules')">✕</button>
            </div>
            <div class="wiz-modal-body">
                <div class="rule-input-row">
                    <input type="text" id="ruleInput" placeholder="Type a rule and press Enter or click Add..." 
                           onkeydown="if(event.key==='Enter'){event.preventDefault();addRule();}">
                    <button class="rule-add-btn" onclick="addRule()">+ Add</button>
                </div>
                <ul class="rules-list" id="rulesList"></ul>
                <div id="rulesEmpty" class="empty-day-msg">No rules added yet. Start typing above.</div>
            </div>
            <div class="wiz-modal-footer">
                <button class="wiz-btn wiz-btn-ghost" onclick="closeModal('rules')">Cancel</button>
                <button class="wiz-btn wiz-btn-primary" onclick="saveRules()">Save Rules</button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════ -->
    <!-- MATERIALS MODAL                                         -->
    <!-- ════════════════════════════════════════════════════════ -->
    <div class="wiz-modal-overlay" id="modal-materials">
        <div class="wiz-modal">
            <div class="wiz-modal-header">
                <h3>🔧 Materials — Equipment & Supplies</h3>
                <button class="wiz-modal-close" onclick="closeModal('materials')">✕</button>
            </div>
            <div class="wiz-modal-body">
                <div class="materials-grid" id="materialsGrid">
                    <!-- Filled by JS -->
                </div>
                <div class="custom-material-row">
                    <input type="text" id="customMaterialInput" placeholder="Add other material...">
                    <button class="custom-material-add-btn" onclick="addCustomMaterial()">+ Add</button>
                </div>
            </div>
            <div class="wiz-modal-footer">
                <button class="wiz-btn wiz-btn-ghost" onclick="closeModal('materials')">Cancel</button>
                <button class="wiz-btn wiz-btn-primary" onclick="saveMaterials()">Save Materials</button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
    lucide.createIcons();

    // ── State ──────────────────────────────────────────────────
    const EVENT_ID = <?= (int)$eventId ?>;
    const DAYS = <?= $daysJson ?>;
    let currentDayIdx = 0;

    // Planning data: { dayIndex: [ {start, end, title}, ... ] }
    let planningData = {};
    DAYS.forEach((_, i) => planningData[i] = []);

    // Rules data
    let rulesData = [];

    // Materials data
    const PREDEFINED_MATERIALS = [
        { id: 'projector', icon: '🖥️', label: 'Projector / Screen' },
        { id: 'microphone', icon: '🎤', label: 'Microphone / Sound' },
        { id: 'whiteboard', icon: '📋', label: 'Whiteboard / Flipchart' },
        { id: 'laptops', icon: '💻', label: 'Laptops / Tablets' },
        { id: 'tables_chairs', icon: '🪑', label: 'Tables & Chairs' },
        { id: 'power', icon: '🔌', label: 'Extension Cords' },
        { id: 'camera', icon: '📸', label: 'Camera / Recording' },
        { id: 'printer', icon: '🖨️', label: 'Printer / Scanner' },
        { id: 'arts', icon: '🎨', label: 'Arts & Crafts' },
        { id: 'storage', icon: '📦', label: 'Storage / Boxes' },
        { id: 'cleaning', icon: '🧹', label: 'Cleaning Supplies' },
        { id: 'catering', icon: '🍽️', label: 'Catering Equipment' }
    ];
    let selectedMaterials = new Set();
    let customMaterials = [];

    // What's been configured
    let configuredResources = {}; // { planning: {...}, rules: {...}, materials: {...} }

    // ── Modal open/close ──────────────────────────────────────
    function openModal(type) {
        document.getElementById('modal-' + type).classList.add('open');
        document.body.style.overflow = 'hidden';
        if (type === 'planning') renderPlanningDay();
        if (type === 'materials') renderMaterialsGrid();
        if (type === 'rules') renderRulesList();
    }
    function closeModal(type) {
        document.getElementById('modal-' + type).classList.remove('open');
        document.body.style.overflow = '';
    }

    // ══════════════════════════════════════════════════════════
    // PLANNING LOGIC
    // ══════════════════════════════════════════════════════════

    function navDay(dir) {
        // Save current day's slots before navigating
        saveDaySlotsToState();
        currentDayIdx = Math.max(0, Math.min(DAYS.length - 1, currentDayIdx + dir));
        renderPlanningDay();
    }

    function renderPlanningDay() {
        const day = DAYS[currentDayIdx];
        document.getElementById('dayLabel').textContent = 'Day ' + day.dayNum + ' of ' + DAYS.length;
        document.getElementById('dayDate').textContent = day.label;
        document.getElementById('prevDayBtn').disabled = currentDayIdx === 0;
        document.getElementById('nextDayBtn').disabled = currentDayIdx === DAYS.length - 1;

        const container = document.getElementById('slotsContainer');
        container.innerHTML = '';

        const slots = planningData[currentDayIdx] || [];
        if (slots.length === 0) {
            container.innerHTML = '<div class="empty-day-msg">No activities for this day. Click "+ Add Activity" below.</div>';
        } else {
            slots.forEach((slot, idx) => {
                container.appendChild(createSlotElement(slot, idx));
            });
        }
    }

    function createSlotElement(slot, idx) {
        const div = document.createElement('div');
        div.className = 'activity-slot';
        div.innerHTML = `
            <input type="time" value="${slot.start || ''}" data-idx="${idx}" data-field="start" placeholder="Start">
            <input type="time" value="${slot.end || ''}" data-idx="${idx}" data-field="end" placeholder="End">
            <input type="text" value="${slot.title || ''}" data-idx="${idx}" data-field="title" placeholder="Activity title...">
            <button class="slot-delete-btn" onclick="deleteSlot(${idx})">✕</button>
        `;
        return div;
    }

    function addSlot() {
        saveDaySlotsToState();
        planningData[currentDayIdx].push({ start: '', end: '', title: '' });
        renderPlanningDay();
    }

    function deleteSlot(idx) {
        saveDaySlotsToState();
        planningData[currentDayIdx].splice(idx, 1);
        renderPlanningDay();
    }

    function saveDaySlotsToState() {
        const container = document.getElementById('slotsContainer');
        const slotEls = container.querySelectorAll('.activity-slot');
        const slots = [];
        slotEls.forEach(el => {
            const inputs = el.querySelectorAll('input');
            slots.push({
                start: inputs[0].value,
                end: inputs[1].value,
                title: inputs[2].value
            });
        });
        planningData[currentDayIdx] = slots;
    }

    function savePlanning() {
        saveDaySlotsToState();
        // Count total slots
        let totalSlots = 0;
        let daysWithSlots = 0;
        const planningExport = [];
        DAYS.forEach((day, i) => {
            const slots = planningData[i].filter(s => s.title.trim() !== '');
            if (slots.length > 0) daysWithSlots++;
            totalSlots += slots.length;
            planningExport.push({ day: day.dayNum, date: day.date, label: day.label, slots });
        });

        if (totalSlots === 0) {
            Swal.fire({ icon: 'warning', title: 'Empty Planning', text: 'Add at least one activity.', confirmButtonColor: '#6366f1' });
            return;
        }

        configuredResources.planning = {
            type: 'planning',
            title: 'Event Planning',
            description: JSON.stringify(planningExport),
            meta: `${totalSlots} activities across ${daysWithSlots} day${daysWithSlots > 1 ? 's' : ''}`
        };

        document.getElementById('card-planning').classList.add('configured');
        closeModal('planning');
        renderDropZone();
        Swal.fire({ icon: 'success', title: 'Planning saved!', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
    }

    // ══════════════════════════════════════════════════════════
    // RULES LOGIC
    // ══════════════════════════════════════════════════════════

    function addRule() {
        const input = document.getElementById('ruleInput');
        const text = input.value.trim();
        if (!text) return;
        rulesData.push(text);
        input.value = '';
        renderRulesList();
        input.focus();
    }

    function deleteRule(idx) {
        rulesData.splice(idx, 1);
        renderRulesList();
    }

    function renderRulesList() {
        const list = document.getElementById('rulesList');
        const empty = document.getElementById('rulesEmpty');
        list.innerHTML = '';
        if (rulesData.length === 0) {
            empty.style.display = 'block';
            return;
        }
        empty.style.display = 'none';
        rulesData.forEach((rule, i) => {
            const li = document.createElement('li');
            li.innerHTML = `
                <span class="rule-number">${i + 1}</span>
                <span class="rule-text">${escapeHtml(rule)}</span>
                <button class="rule-delete-btn" onclick="deleteRule(${i})">✕</button>
            `;
            list.appendChild(li);
        });
    }

    function saveRules() {
        if (rulesData.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No Rules', text: 'Add at least one rule.', confirmButtonColor: '#f59e0b' });
            return;
        }

        configuredResources.rules = {
            type: 'regles',
            title: 'Event Rules',
            description: JSON.stringify(rulesData),
            meta: `${rulesData.length} rule${rulesData.length > 1 ? 's' : ''}`
        };

        document.getElementById('card-rules').classList.add('configured');
        closeModal('rules');
        renderDropZone();
        Swal.fire({ icon: 'success', title: 'Rules saved!', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
    }

    // ══════════════════════════════════════════════════════════
    // MATERIALS LOGIC
    // ══════════════════════════════════════════════════════════

    function renderMaterialsGrid() {
        const grid = document.getElementById('materialsGrid');
        grid.innerHTML = '';

        // Predefined
        PREDEFINED_MATERIALS.forEach(mat => {
            const div = document.createElement('div');
            div.className = 'material-item' + (selectedMaterials.has(mat.id) ? ' selected' : '');
            div.onclick = () => toggleMaterial(mat.id);
            div.innerHTML = `
                <span class="material-icon">${mat.icon}</span>
                <span class="material-label">${mat.label}</span>
            `;
            grid.appendChild(div);
        });

        // Custom ones
        customMaterials.forEach((mat, idx) => {
            const div = document.createElement('div');
            div.className = 'material-item selected';
            div.innerHTML = `
                <span class="material-icon">🏷️</span>
                <span class="material-label">${escapeHtml(mat)}</span>
            `;
            div.onclick = () => { customMaterials.splice(idx, 1); renderMaterialsGrid(); };
            grid.appendChild(div);
        });
    }

    function toggleMaterial(id) {
        if (selectedMaterials.has(id)) {
            selectedMaterials.delete(id);
        } else {
            selectedMaterials.add(id);
        }
        renderMaterialsGrid();
    }

    function addCustomMaterial() {
        const input = document.getElementById('customMaterialInput');
        const val = input.value.trim();
        if (!val) return;
        customMaterials.push(val);
        input.value = '';
        renderMaterialsGrid();
    }

    function saveMaterials() {
        const allMats = [
            ...Array.from(selectedMaterials).map(id => {
                const m = PREDEFINED_MATERIALS.find(p => p.id === id);
                return m ? m.label : id;
            }),
            ...customMaterials
        ];

        if (allMats.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No Materials', text: 'Select at least one material.', confirmButtonColor: '#10b981' });
            return;
        }

        configuredResources.materials = {
            type: 'materiel',
            title: 'Event Materials',
            description: JSON.stringify(allMats),
            meta: `${allMats.length} item${allMats.length > 1 ? 's' : ''}`
        };

        document.getElementById('card-materials').classList.add('configured');
        closeModal('materials');
        renderDropZone();
        Swal.fire({ icon: 'success', title: 'Materials saved!', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
    }

    // ══════════════════════════════════════════════════════════
    // DROP ZONE
    // ══════════════════════════════════════════════════════════

    function renderDropZone() {
        const zone = document.getElementById('dropZone');
        const placeholder = document.getElementById('dropPlaceholder');
        const keys = Object.keys(configuredResources);

        if (keys.length === 0) {
            zone.classList.remove('has-items');
            placeholder.style.display = 'block';
            document.getElementById('btnConfirm').disabled = true;
            return;
        }

        zone.classList.add('has-items');
        placeholder.style.display = 'none';
        document.getElementById('btnConfirm').disabled = false;

        // Clear existing chips
        zone.querySelectorAll('.resource-chip').forEach(c => c.remove());

        const icons = { planning: '📅', regles: '📜', materiel: '🔧' };
        const labels = { planning: 'Planning', regles: 'Rules', materiel: 'Materials' };

        keys.forEach(key => {
            const r = configuredResources[key];
            const chip = document.createElement('div');
            chip.className = 'resource-chip';
            chip.innerHTML = `
                <span class="chip-icon">${icons[r.type] || '📄'}</span>
                <div class="chip-info">
                    <span class="chip-title">${labels[r.type] || r.title}</span>
                    <span class="chip-meta">${r.meta}</span>
                </div>
                <div class="chip-actions">
                    <button class="chip-btn" onclick="openModal('${key}')" title="Edit">✎</button>
                    <button class="chip-btn delete" onclick="removeResource('${key}')" title="Remove">✕</button>
                </div>
            `;
            zone.appendChild(chip);
        });
    }

    function removeResource(key) {
        delete configuredResources[key];
        const typeMap = { planning: 'card-planning', rules: 'card-rules', materials: 'card-materials' };
        const cardId = typeMap[key];
        if (cardId) document.getElementById(cardId).classList.remove('configured');
        renderDropZone();
    }

    // ══════════════════════════════════════════════════════════
    // SUBMIT
    // ══════════════════════════════════════════════════════════

    async function submitResources() {
        const resources = Object.values(configuredResources).map(r => ({
            type: r.type,
            title: r.title,
            description: r.description
        }));

        if (resources.length === 0) return;

        document.getElementById('btnConfirm').disabled = true;
        document.getElementById('btnConfirm').innerHTML = '<span class="spinner"></span> Saving...';

        try {
            const formData = new FormData();
            formData.append('event_id', EVENT_ID);
            formData.append('resources', JSON.stringify(resources));

            const resp = await fetch('../../index.php?action=save_event_resources', {
                method: 'POST',
                body: formData
            });
            const result = await resp.json();

            if (result.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Resources Saved!',
                    text: `${result.count} resource(s) linked to your event.`,
                    confirmButtonColor: '#6366f1',
                    confirmButtonText: 'View Event'
                });
                window.location.href = 'event_details.php?id=' + EVENT_ID;
            } else {
                throw new Error(result.message || 'Failed to save.');
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message, confirmButtonColor: '#ef4444' });
            document.getElementById('btnConfirm').disabled = false;
            document.getElementById('btnConfirm').innerHTML = '<i data-lucide="check-circle"></i> Confirm Resources';
            lucide.createIcons();
        }
    }

    // ── Utility ──
    function escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    // ── Pre-fill Existing Data ──
    const initialData = <?= $initialDataJson ?>;

    if (initialData.planning) {
        try {
            const raw = JSON.parse(initialData.planning.description);
            raw.forEach(day => {
                planningData[day.day - 1] = day.slots || [];
            });
            let totalSlots = 0, daysWithSlots = 0;
            raw.forEach(day => {
                if (day.slots && day.slots.length > 0) {
                    daysWithSlots++;
                    totalSlots += day.slots.length;
                }
            });
            configuredResources.planning = {
                type: 'planning',
                title: initialData.planning.title,
                description: initialData.planning.description,
                meta: `${totalSlots} activities across ${daysWithSlots} day${daysWithSlots > 1 ? 's' : ''}`
            };
            document.getElementById('card-planning').classList.add('configured');
        } catch (e) { console.error('Failed to parse planning', e); }
    }

    if (initialData.rules) {
        try {
            rulesData = JSON.parse(initialData.rules.description) || [];
            configuredResources.rules = {
                type: 'regles',
                title: initialData.rules.title,
                description: initialData.rules.description,
                meta: `${rulesData.length} rule${rulesData.length > 1 ? 's' : ''}`
            };
            document.getElementById('card-rules').classList.add('configured');
        } catch (e) { console.error('Failed to parse rules', e); }
    }

    if (initialData.materials) {
        try {
            const mats = JSON.parse(initialData.materials.description) || [];
            mats.forEach(matLabel => {
                const predef = PREDEFINED_MATERIALS.find(p => p.label === matLabel);
                if (predef) {
                    selectedMaterials.add(predef.id);
                } else {
                    customMaterials.push(matLabel);
                }
            });
            configuredResources.materials = {
                type: 'materiel',
                title: initialData.materials.title,
                description: initialData.materials.description,
                meta: `${mats.length} item${mats.length > 1 ? 's' : ''}`
            };
            document.getElementById('card-materials').classList.add('configured');
        } catch (e) { console.error('Failed to parse materials', e); }
    }

    // ── Init ──
    renderPlanningDay();
    renderMaterialsGrid();
    renderDropZone(); // make sure existing items show up
    </script>
</body>
</html>
