<?php

/**
 * License Widget für Dashboard
 * Zeigt den aktuellen SYSTEM-Lizenzstatus im Dashboard an
 */

// Dieses Widget kann in dashboard.php eingebunden werden
$licenseInfo = $auth->getLicenseHelper()->getLicenseFromSession();
$globalLicenseKey = $_SESSION['global_license_key'] ?? null;
$isAdmin = $auth->isAdmin();
?>

<?php if ($globalLicenseKey && $licenseInfo): ?>
    <!-- Lizenz-Status Widget -->
    <div class="dashboard-widget license-widget">
        <div class="widget-header">
            <h3><i class="fas fa-key"></i> System-Lizenz</h3>
            <?php if ($licenseInfo['valid']): ?>
                <span class="status-badge active">Aktiv</span>
            <?php else: ?>
                <span class="status-badge expired">Ungültig</span>
            <?php endif; ?>
        </div>

        <div class="widget-content">
            <div class="license-details">
                <div class="detail-item">
                    <span class="detail-label">Lizenzschlüssel:</span>
                    <span class="detail-value"><?= htmlspecialchars(substr($globalLicenseKey, 0, 7)) ?>-****</span>
                </div>

                <?php if ($licenseInfo['expires_at']): ?>
                    <?php
                    $daysLeft = floor(($licenseInfo['expires_at'] / 1000 - time()) / 86400);
                    $expiryClass = $daysLeft <= 30 ? 'warning' : '';
                    ?>
                    <div class="detail-item <?= $expiryClass ?>">
                        <span class="detail-label">Gültig bis:</span>
                        <span class="detail-value">
                            <?= date('d.m.Y', $licenseInfo['expires_at'] / 1000) ?>
                            <?php if ($daysLeft > 0): ?>
                                <small>(noch <?= $daysLeft ?> Tage)</small>
                            <?php else: ?>
                                <small class="expired">Abgelaufen!</small>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if (isset($licenseInfo['features']) && count($licenseInfo['features']) > 0): ?>
                    <div class="detail-item">
                        <span class="detail-label">Features:</span>
                        <div class="feature-pills">
                            <?php foreach (array_slice($licenseInfo['features'], 0, 3) as $feature): ?>
                                <span class="feature-pill"><?= htmlspecialchars($feature) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($licenseInfo['features']) > 3): ?>
                                <span class="feature-pill">+<?= count($licenseInfo['features']) - 3 ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="widget-actions">
                <?php if ($isAdmin): ?>
                    <a href="modules/settings/license.php" class="btn-small">
                        <i class="fas fa-cog"></i> Lizenz verwalten
                    </a>
                <?php else: ?>
                    <span class="detail-label" style="font-size: 12px; color: var(--clr-surface-a50);">
                        <i class="fas fa-info-circle"></i> Systemlizenz (Admin-Verwaltung)
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Keine Lizenz Widget -->
    <div class="dashboard-widget license-widget no-license">
        <div class="widget-header">
            <h3><i class="fas fa-key"></i> System-Lizenz</h3>
            <span class="status-badge inactive">Keine Lizenz</span>
        </div>

        <div class="widget-content">
            <p class="no-license-text">
                <i class="fas fa-exclamation-triangle"></i>
                Keine Systemlizenz aktiviert. <?= $isAdmin ? 'Bitte aktivieren Sie eine Lizenz.' : 'Bitte kontaktieren Sie einen Administrator.' ?>
            </p>
            <div class="widget-actions">
                <?php if ($isAdmin): ?>
                    <a href="modules/settings/license.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Lizenz aktivieren
                    </a>
                <?php else: ?>
                    <span class="detail-label" style="font-size: 12px; color: var(--clr-surface-a50);">
                        <i class="fas fa-lock"></i> Nur Admins können die Lizenz verwalten
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
    .license-widget {
        background: var(--clr-surface-a05);
        border: 1px solid var(--clr-surface-a10);
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .widget-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--clr-surface-a10);
    }

    .widget-header h3 {
        margin: 0;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .status-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-badge.active {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
    }

    .status-badge.expired {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
    }

    .status-badge.inactive {
        background: var(--clr-surface-a10);
        color: var(--clr-surface-a50);
    }

    .license-details {
        margin-bottom: 15px;
    }

    .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        font-size: 14px;
    }

    .detail-item.warning {
        color: #fbbf24;
    }

    .detail-label {
        color: var(--clr-surface-a50);
    }

    .detail-value {
        font-weight: 500;
    }

    .detail-value small {
        font-weight: normal;
        color: var(--clr-surface-a50);
        margin-left: 8px;
    }

    .detail-value small.expired {
        color: #ef4444;
    }

    .feature-pills {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .feature-pill {
        padding: 2px 8px;
        background: var(--clr-primary-a0);
        color: white;
        border-radius: 10px;
        font-size: 11px;
    }

    .widget-actions {
        display: flex;
        gap: 10px;
    }

    .btn-small,
    .btn-primary {
        padding: 6px 12px;
        border-radius: 4px;
        text-decoration: none;
        font-size: 13px;
        transition: opacity 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: none;
        cursor: pointer;
    }

    .btn-small {
        background: var(--clr-surface-a10);
        color: var(--clr-text);
    }

    .btn-primary {
        background: var(--clr-primary-a0);
        color: white;
    }

    .btn-small:hover,
    .btn-primary:hover {
        opacity: 0.8;
    }

    .no-license-text {
        color: var(--clr-surface-a60);
        margin: 10px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .no-license-text i {
        color: #fbbf24;
    }
</style>