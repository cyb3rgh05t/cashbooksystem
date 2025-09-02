<?php

/**
 * Debug Widget für License-Checks
 * Zeigt im Dashboard die Lizenz-Prüfungen an
 */

// Hole Debug-Logs
$debugLogs = $auth->getDebugLogs();
$lastCheck = $_SESSION['last_license_check'] ?? 0;
$timeSinceCheck = time() - $lastCheck;
$nextCheckIn = max(0, 1800 - $timeSinceCheck); // Check alle 60 Sekunden
?>

<!-- Debug Console -->
<div id="debug-console" style="
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 400px;
    max-height: 300px;
    background: #1a1a1a;
    color: #0f0;
    border: 2px solid #0f0;
    border-radius: 8px;
    padding: 10px;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    z-index: 9999;
    box-shadow: 0 0 20px rgba(0,255,0,0.3);
">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid #0f0; padding-bottom: 5px;">
        <strong style="color: #0f0;">🔍 LICENSE DEBUG CONSOLE</strong>
        <button onclick="document.getElementById('debug-console').style.display='none'" style="
            background: transparent;
            border: 1px solid #0f0;
            color: #0f0;
            cursor: pointer;
            padding: 2px 8px;
            font-size: 10px;
        ">CLOSE</button>
    </div>

    <div style="margin-bottom: 10px; padding: 5px; background: #0a0a0a; border-radius: 4px;">
        <div>⏱️ Next check in: <span id="next-check" style="color: #ff0;"><?= $nextCheckIn ?>s</span></div>
        <div>📍 Last check: <?= $lastCheck ? date('H:i:s', $lastCheck) : 'Never' ?></div>
        <div>🔑 License: <?= isset($_SESSION['global_license_key']) ? substr($_SESSION['global_license_key'], 0, 7) . '...' : 'NONE' ?></div>
        <div>👤 User: <?= $_SESSION['username'] ?? 'Unknown' ?> (<?= $_SESSION['user_role'] ?? 'user' ?>)</div>
    </div>

    <div style="height: 150px; overflow-y: auto; background: #0a0a0a; padding: 5px; border-radius: 4px;">
        <?php if (empty($debugLogs)): ?>
            <div style="color: #666;">Waiting for logs...</div>
        <?php else: ?>
            <?php foreach (array_reverse(array_slice($debugLogs, -20)) as $log): ?>
                <div style="margin-bottom: 3px; color: #0f0; opacity: 0.8;">
                    <?= htmlspecialchars($log) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div style="margin-top: 10px; font-size: 10px; color: #666;">
        License checks every 60 seconds | Auto-logout on failure
    </div>
</div>

<script>
    // Countdown Timer
    setInterval(function() {
        const el = document.getElementById('next-check');
        if (el) {
            let seconds = parseInt(el.textContent);
            seconds--;
            if (seconds < 0) {
                seconds = 60;
                el.style.color = '#0f0';
                el.textContent = 'CHECKING NOW...';
                setTimeout(() => {
                    location.reload(); // Reload to trigger check
                }, 1000);
            } else {
                el.textContent = seconds + 's';
                if (seconds < 10) {
                    el.style.color = '#f00';
                } else if (seconds < 30) {
                    el.style.color = '#fa0';
                } else {
                    el.style.color = '#ff0';
                }
            }
        }
    }, 1000);

    // Auto-refresh Console alle 5 Sekunden via AJAX
    setInterval(function() {
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                // Parse nur die Logs aus der Antwort
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newConsole = doc.getElementById('debug-console');
                if (newConsole) {
                    const currentConsole = document.getElementById('debug-console');
                    if (currentConsole && currentConsole.style.display !== 'none') {
                        // Update nur den Log-Bereich
                        const logsDiv = newConsole.querySelector('div[style*="height: 150px"]');
                        const currentLogsDiv = currentConsole.querySelector('div[style*="height: 150px"]');
                        if (logsDiv && currentLogsDiv) {
                            currentLogsDiv.innerHTML = logsDiv.innerHTML;
                        }
                    }
                }
            })
            .catch(err => console.error('Debug refresh failed:', err));
    }, 5000);

    // Console Log für Browser
    console.log('%c🔐 LICENSE SYSTEM ACTIVE', 'color: #0f0; font-size: 16px; font-weight: bold;');
    console.log('Next license check in:', document.getElementById('next-check')?.textContent);
    console.log('License key:', '<?= isset($_SESSION['global_license_key']) ? substr($_SESSION['global_license_key'], 0, 7) . '...' : 'NONE' ?>');
</script>