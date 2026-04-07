# PowerShell script to fix profile.html
$filePath = "d:\XAMPP\htdocs\integrated_version2\auth\profile.html"
$content = Get-Content $filePath -Raw -Encoding UTF8

# Fix 1: Add data attributes to stats
$content = $content -replace '(<div class="text-xl font-semibold text-white tracking-tight")>12</div>\s*(<div class="text-\[10px\] text-zinc-400">Actions Joined)', '$1 data-stat="actions-joined">0</div>$2'
$content = $content -replace '(<div class="text-xl font-semibold text-white tracking-tight")>8</div>\s*(<div class="text-\[10px\] text-zinc-400">Resources Shared)', '$1 data-stat="resources-shared">0</div>$2'

# Fix 2: Change Title/Role to Email in edit form
$content = $content -replace '(<label class="text-\[10px\] font-medium text-zinc-500 uppercase tracking-wider">)Title/Role(</label>)', '$1Email$2'
$content = $content -replace '(<input type=")text(" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder=")e\.g\. Humanitarian(")', '$1email$2you@example.com$3'
$content = $content -replace '(<input type="text" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="e\.g\. Humanitarian")>', '<input type="email" id="editEmail" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="you@example.com">'

# Fix 3: Change calendar icon to map-pin and add locationText
$content = $content -replace '(<i data-lucide=")calendar(" class="w-3 h-3"></i>)\s*Joined Sept 2023', '$1map-pin$2<span id="locationText">Global Citizen</span>'

# Save the modified content
$content | Set-Content $filePath -Encoding UTF8 -NoNewline

Write-Host "Profile.html fixed successfully!" -ForegroundColor Green
