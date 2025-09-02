<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/init_logger.php';
require_once '../../includes/role_check.php';

// Require login mit Auth-Klasse
$auth->requireLogin();

// Get current user
$currentUser = $auth->getCurrentUser();
restrictViewerAccess($currentUser);
$user_id = $currentUser['id'];

// Database connection
$db = new Database();
$pdo = $db->getConnection();

$category_id = $_GET['id'] ?? '';

// Kategorie laden und Berechtigung prüfen
if (empty($category_id)) {
    $_SESSION['error'] = 'Keine Kategorie angegeben.';
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$category_id]);
$category = $stmt->fetch();

if (!$category) {
    $_SESSION['error'] = 'Kategorie nicht gefunden oder keine Berechtigung.';
    header('Location: index.php');
    exit;
}

// Statistiken zur Kategorie laden (FIXED: date statt transaction_date)
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as transaction_count,
        COALESCE(SUM(amount), 0) as total_amount,
        MAX(date) as last_used
    FROM transactions 
    WHERE category_id = ?
");
$stmt->execute([$category_id]);
$stats = $stmt->fetch();

// FontAwesome Icons statt Emojis
$predefined_icons = [
    // 💰 Finanzen & Business
    '<i class="fa-solid fa-sack-dollar"></i>',
    '<i class="fa-solid fa-dollar-sign"></i>',
    '<i class="fa-solid fa-euro-sign"></i>',
    '<i class="fa-solid fa-briefcase"></i>',
    '<i class="fa-solid fa-chart-line"></i>',
    '<i class="fa-solid fa-chart-bar"></i>',
    '<i class="fa-solid fa-chart-pie"></i>',
    '<i class="fa-solid fa-chart-area"></i>',
    '<i class="fa-solid fa-credit-card"></i>',
    '<i class="fa-solid fa-coins"></i>',
    '<i class="fa-solid fa-building"></i>',
    '<i class="fa-solid fa-handshake"></i>',
    '<i class="fa-solid fa-piggy-bank"></i>',
    '<i class="fa-solid fa-receipt"></i>',
    '<i class="fa-solid fa-calculator"></i>',
    '<i class="fa-solid fa-percent"></i>',
    '<i class="fa-solid fa-money-bill"></i>',
    '<i class="fa-solid fa-money-bill-wave"></i>',
    '<i class="fa-solid fa-wallet"></i>',
    '<i class="fa-solid fa-file-invoice-dollar"></i>',
    '<i class="fa-solid fa-cash-register"></i>',
    '<i class="fa-solid fa-landmark"></i>',
    '<i class="fa-solid fa-scale-balanced"></i>',
    '<i class="fa-solid fa-comment-dollar"></i>',
    '<i class="fa-solid fa-hand-holding-dollar"></i>',

    // 🏠 Haushalt & Leben
    '<i class="fa-solid fa-house"></i>',
    '<i class="fa-solid fa-home"></i>',
    '<i class="fa-solid fa-bed"></i>',
    '<i class="fa-solid fa-couch"></i>',
    '<i class="fa-solid fa-shower"></i>',
    '<i class="fa-solid fa-toilet"></i>',
    '<i class="fa-solid fa-broom"></i>',
    '<i class="fa-solid fa-soap"></i>',
    '<i class="fa-solid fa-lightbulb"></i>',
    '<i class="fa-solid fa-plug"></i>',
    '<i class="fa-solid fa-wrench"></i>',
    '<i class="fa-solid fa-hammer"></i>',
    '<i class="fa-solid fa-paint-roller"></i>',
    '<i class="fa-solid fa-toolbox"></i>',
    '<i class="fa-solid fa-screwdriver"></i>',
    '<i class="fa-solid fa-faucet"></i>',
    '<i class="fa-solid fa-blender"></i>',
    '<i class="fa-solid fa-chair"></i>',
    '<i class="fa-solid fa-fan"></i>',
    '<i class="fa-solid fa-door-open"></i>',
    '<i class="fa-solid fa-door-closed"></i>',
    '<i class="fa-solid fa-key"></i>',
    '<i class="fa-solid fa-temperature-high"></i>',
    '<i class="fa-solid fa-temperature-low"></i>',

    // 🚗 Transport & Reisen
    '<i class="fa-solid fa-car"></i>',
    '<i class="fa-solid fa-car-side"></i>',
    '<i class="fa-solid fa-bicycle"></i>',
    '<i class="fa-solid fa-train"></i>',
    '<i class="fa-solid fa-bus"></i>',
    '<i class="fa-solid fa-plane"></i>',
    '<i class="fa-solid fa-ship"></i>',
    '<i class="fa-solid fa-motorcycle"></i>',
    '<i class="fa-solid fa-gas-pump"></i>',
    '<i class="fa-solid fa-parking"></i>',
    '<i class="fa-solid fa-taxi"></i>',
    '<i class="fa-solid fa-map"></i>',
    '<i class="fa-solid fa-map-location-dot"></i>',
    '<i class="fa-solid fa-suitcase"></i>',
    '<i class="fa-solid fa-helicopter"></i>',
    '<i class="fa-solid fa-rocket"></i>',
    '<i class="fa-solid fa-road"></i>',
    '<i class="fa-solid fa-traffic-light"></i>',
    '<i class="fa-solid fa-compass"></i>',
    '<i class="fa-solid fa-anchor"></i>',
    '<i class="fa-solid fa-truck"></i>',
    '<i class="fa-solid fa-van-shuttle"></i>',

    // 🛒 Shopping & Lifestyle
    '<i class="fa-solid fa-cart-shopping"></i>',
    '<i class="fa-solid fa-bag-shopping"></i>',
    '<i class="fa-solid fa-store"></i>',
    '<i class="fa-solid fa-shirt"></i>',
    '<i class="fa-solid fa-gem"></i>',
    '<i class="fa-solid fa-glasses"></i>',
    '<i class="fa-solid fa-shoe-prints"></i>',
    '<i class="fa-solid fa-scissors"></i>',
    '<i class="fa-solid fa-spray-can"></i>',
    '<i class="fa-solid fa-tag"></i>',
    '<i class="fa-solid fa-tags"></i>',
    '<i class="fa-solid fa-barcode"></i>',
    '<i class="fa-solid fa-qrcode"></i>',
    '<i class="fa-solid fa-basket-shopping"></i>',
    '<i class="fa-solid fa-crown"></i>',
    '<i class="fa-solid fa-ring"></i>',
    '<i class="fa-solid fa-hat-cowboy"></i>',
    '<i class="fa-solid fa-mitten"></i>',
    '<i class="fa-solid fa-socks"></i>',
    '<i class="fa-solid fa-vest"></i>',

    // 🍕 Essen & Trinken
    '<i class="fa-solid fa-pizza-slice"></i>',
    '<i class="fa-solid fa-burger"></i>',
    '<i class="fa-solid fa-utensils"></i>',
    '<i class="fa-solid fa-mug-hot"></i>',
    '<i class="fa-solid fa-wine-glass"></i>',
    '<i class="fa-solid fa-beer-mug-empty"></i>',
    '<i class="fa-solid fa-ice-cream"></i>',
    '<i class="fa-solid fa-cookie"></i>',
    '<i class="fa-solid fa-apple-whole"></i>',
    '<i class="fa-solid fa-carrot"></i>',
    '<i class="fa-solid fa-fish"></i>',
    '<i class="fa-solid fa-cheese"></i>',
    '<i class="fa-solid fa-bread-slice"></i>',
    '<i class="fa-solid fa-egg"></i>',
    '<i class="fa-solid fa-bacon"></i>',
    '<i class="fa-solid fa-hotdog"></i>',
    '<i class="fa-solid fa-drumstick-bite"></i>',
    '<i class="fa-solid fa-bowl-rice"></i>',
    '<i class="fa-solid fa-bowl-food"></i>',
    '<i class="fa-solid fa-bottle-water"></i>',
    '<i class="fa-solid fa-champagne-glasses"></i>',
    '<i class="fa-solid fa-martini-glass"></i>',
    '<i class="fa-solid fa-whiskey-glass"></i>',
    '<i class="fa-solid fa-cake-candles"></i>',
    '<i class="fa-solid fa-lemon"></i>',
    '<i class="fa-solid fa-pepper-hot"></i>',
    '<i class="fa-solid fa-plate-wheat"></i>',
    '<i class="fa-solid fa-mug-saucer"></i>',

    // 📱 Technologie
    '<i class="fa-solid fa-laptop"></i>',
    '<i class="fa-solid fa-mobile"></i>',
    '<i class="fa-solid fa-mobile-screen"></i>',
    '<i class="fa-solid fa-desktop"></i>',
    '<i class="fa-solid fa-tablet"></i>',
    '<i class="fa-solid fa-headphones"></i>',
    '<i class="fa-solid fa-camera"></i>',
    '<i class="fa-solid fa-tv"></i>',
    '<i class="fa-solid fa-gamepad"></i>',
    '<i class="fa-solid fa-wifi"></i>',
    '<i class="fa-solid fa-phone"></i>',
    '<i class="fa-solid fa-microchip"></i>',
    '<i class="fa-solid fa-keyboard"></i>',
    '<i class="fa-solid fa-computer-mouse"></i>',
    '<i class="fa-solid fa-hard-drive"></i>',
    '<i class="fa-solid fa-print"></i>',
    '<i class="fa-solid fa-fax"></i>',
    '<i class="fa-solid fa-server"></i>',
    '<i class="fa-solid fa-database"></i>',
    '<i class="fa-solid fa-robot"></i>',
    '<i class="fa-solid fa-satellite-dish"></i>',
    '<i class="fa-solid fa-sim-card"></i>',
    '<i class="fa-solid fa-ethernet"></i>',
    '<i class="fa-solid fa-bluetooth"></i>',
    '<i class="fa-solid fa-compact-disc"></i>',
    '<i class="fa-solid fa-power-off"></i>',
    '<i class="fa-solid fa-plug-circle-check"></i>',
    '<i class="fa-solid fa-battery-full"></i>',

    // 🏥 Gesundheit & Wellness
    '<i class="fa-solid fa-hospital"></i>',
    '<i class="fa-solid fa-pills"></i>',
    '<i class="fa-solid fa-stethoscope"></i>',
    '<i class="fa-solid fa-heart-pulse"></i>',
    '<i class="fa-solid fa-tooth"></i>',
    '<i class="fa-solid fa-eye"></i>',
    '<i class="fa-solid fa-dumbbell"></i>',
    '<i class="fa-solid fa-spa"></i>',
    '<i class="fa-solid fa-leaf"></i>',
    '<i class="fa-solid fa-syringe"></i>',
    '<i class="fa-solid fa-thermometer"></i>',
    '<i class="fa-solid fa-band-aid"></i>',
    '<i class="fa-solid fa-crutch"></i>',
    '<i class="fa-solid fa-wheelchair"></i>',
    '<i class="fa-solid fa-user-doctor"></i>',
    '<i class="fa-solid fa-user-nurse"></i>',
    '<i class="fa-solid fa-virus"></i>',
    '<i class="fa-solid fa-bacteria"></i>',
    '<i class="fa-solid fa-lungs"></i>',
    '<i class="fa-solid fa-brain"></i>',
    '<i class="fa-solid fa-hand-dots"></i>',
    '<i class="fa-solid fa-smoking"></i>',
    '<i class="fa-solid fa-ban-smoking"></i>',
    '<i class="fa-solid fa-prescription"></i>',
    '<i class="fa-solid fa-prescription-bottle"></i>',
    '<i class="fa-solid fa-capsules"></i>',
    '<i class="fa-solid fa-tablets"></i>',
    '<i class="fa-solid fa-x-ray"></i>',

    // 🎓 Bildung & Arbeit
    '<i class="fa-solid fa-graduation-cap"></i>',
    '<i class="fa-solid fa-book"></i>',
    '<i class="fa-solid fa-pen"></i>',
    '<i class="fa-solid fa-pencil"></i>',
    '<i class="fa-solid fa-chalkboard"></i>',
    '<i class="fa-solid fa-microscope"></i>',
    '<i class="fa-solid fa-flask"></i>',
    '<i class="fa-solid fa-ruler"></i>',
    '<i class="fa-solid fa-paperclip"></i>',
    '<i class="fa-solid fa-thumbtack"></i>',
    '<i class="fa-solid fa-bookmark"></i>',
    '<i class="fa-solid fa-book-open"></i>',
    '<i class="fa-solid fa-school"></i>',
    '<i class="fa-solid fa-atom"></i>',
    '<i class="fa-solid fa-dna"></i>',
    '<i class="fa-solid fa-magnet"></i>',
    '<i class="fa-solid fa-telescope"></i>',
    '<i class="fa-solid fa-user-graduate"></i>',
    '<i class="fa-solid fa-chalkboard-user"></i>',
    '<i class="fa-solid fa-blackboard"></i>',
    '<i class="fa-solid fa-eraser"></i>',
    '<i class="fa-solid fa-highlighter"></i>',
    '<i class="fa-solid fa-marker"></i>',

    // 🎬 Entertainment
    '<i class="fa-solid fa-film"></i>',
    '<i class="fa-solid fa-music"></i>',
    '<i class="fa-solid fa-masks-theater"></i>',
    '<i class="fa-solid fa-ticket"></i>',
    '<i class="fa-solid fa-guitar"></i>',
    '<i class="fa-solid fa-drum"></i>',
    '<i class="fa-solid fa-microphone"></i>',
    '<i class="fa-solid fa-radio"></i>',
    '<i class="fa-solid fa-record-vinyl"></i>',
    '<i class="fa-solid fa-dice"></i>',
    '<i class="fa-solid fa-chess"></i>',
    '<i class="fa-solid fa-puzzle-piece"></i>',
    '<i class="fa-solid fa-video"></i>',
    '<i class="fa-solid fa-clapper-board"></i>',
    '<i class="fa-solid fa-wand-magic-sparkles"></i>',
    '<i class="fa-solid fa-headphones-simple"></i>',
    '<i class="fa-solid fa-compact-disc"></i>',
    '<i class="fa-solid fa-play"></i>',
    '<i class="fa-solid fa-pause"></i>',
    '<i class="fa-solid fa-forward"></i>',
    '<i class="fa-solid fa-backward"></i>',
    '<i class="fa-solid fa-volume-high"></i>',

    // 🏆 Sport & Freizeit
    '<i class="fa-solid fa-trophy"></i>',
    '<i class="fa-solid fa-football"></i>',
    '<i class="fa-solid fa-basketball"></i>',
    '<i class="fa-solid fa-baseball"></i>',
    '<i class="fa-solid fa-golf-ball-tee"></i>',
    '<i class="fa-solid fa-volleyball"></i>',
    '<i class="fa-solid fa-bowling-ball"></i>',
    '<i class="fa-solid fa-table-tennis-paddle-ball"></i>',
    '<i class="fa-solid fa-hockey-puck"></i>',
    '<i class="fa-solid fa-medal"></i>',
    '<i class="fa-solid fa-person-running"></i>',
    '<i class="fa-solid fa-person-swimming"></i>',
    '<i class="fa-solid fa-person-biking"></i>',
    '<i class="fa-solid fa-person-skiing"></i>',
    '<i class="fa-solid fa-person-snowboarding"></i>',
    '<i class="fa-solid fa-person-hiking"></i>',
    '<i class="fa-solid fa-campground"></i>',
    '<i class="fa-solid fa-mountain"></i>',
    '<i class="fa-solid fa-mountain-sun"></i>',
    '<i class="fa-solid fa-tent"></i>',
    '<i class="fa-solid fa-fire-flame-curved"></i>',
    '<i class="fa-solid fa-fishing-rod"></i>',

    // 🐕 Tiere & Natur
    '<i class="fa-solid fa-dog"></i>',
    '<i class="fa-solid fa-cat"></i>',
    '<i class="fa-solid fa-fish"></i>',
    '<i class="fa-solid fa-bird"></i>',
    '<i class="fa-solid fa-tree"></i>',
    '<i class="fa-solid fa-seedling"></i>',
    '<i class="fa-solid fa-horse"></i>',
    '<i class="fa-solid fa-cow"></i>',
    '<i class="fa-solid fa-dragon"></i>',
    '<i class="fa-solid fa-spider"></i>',
    '<i class="fa-solid fa-bug"></i>',
    '<i class="fa-solid fa-feather"></i>',
    '<i class="fa-solid fa-paw"></i>',
    '<i class="fa-solid fa-crow"></i>',
    '<i class="fa-solid fa-wheat-awn"></i>',
    '<i class="fa-solid fa-clover"></i>',
    '<i class="fa-solid fa-hippo"></i>',
    '<i class="fa-solid fa-otter"></i>',
    '<i class="fa-solid fa-frog"></i>',
    '<i class="fa-solid fa-worm"></i>',
    '<i class="fa-solid fa-shrimp"></i>',
    '<i class="fa-solid fa-kiwi-bird"></i>',

    // ⭐ Verschiedenes
    '<i class="fa-solid fa-star"></i>',
    '<i class="fa-solid fa-gift"></i>',
    '<i class="fa-solid fa-heart"></i>',
    '<i class="fa-solid fa-fire"></i>',
    '<i class="fa-solid fa-sun"></i>',
    '<i class="fa-solid fa-moon"></i>',
    '<i class="fa-solid fa-cloud"></i>',
    '<i class="fa-solid fa-cloud-sun"></i>',
    '<i class="fa-solid fa-cloud-rain"></i>',
    '<i class="fa-solid fa-umbrella"></i>',
    '<i class="fa-solid fa-lock"></i>',
    '<i class="fa-solid fa-unlock"></i>',
    '<i class="fa-solid fa-bell"></i>',
    '<i class="fa-solid fa-flag"></i>',
    '<i class="fa-solid fa-bullseye"></i>',
    '<i class="fa-solid fa-globe"></i>',
    '<i class="fa-solid fa-folder"></i>',
    '<i class="fa-solid fa-folder-open"></i>',
    '<i class="fa-solid fa-file"></i>',
    '<i class="fa-solid fa-envelope"></i>',
    '<i class="fa-solid fa-comment"></i>',
    '<i class="fa-solid fa-comments"></i>',
    '<i class="fa-solid fa-calendar"></i>',
    '<i class="fa-solid fa-calendar-days"></i>',
    '<i class="fa-solid fa-clock"></i>',
    '<i class="fa-solid fa-hourglass"></i>',
    '<i class="fa-solid fa-bolt"></i>',
    '<i class="fa-solid fa-snowflake"></i>',
    '<i class="fa-solid fa-wind"></i>',
    '<i class="fa-solid fa-water"></i>',
    '<i class="fa-solid fa-droplet"></i>',
    '<i class="fa-solid fa-earth-americas"></i>',
    '<i class="fa-solid fa-infinity"></i>',
    '<i class="fa-solid fa-yin-yang"></i>',
    '<i class="fa-solid fa-peace"></i>',
    '<i class="fa-solid fa-gavel"></i>',
    '<i class="fa-solid fa-shield"></i>',
    '<i class="fa-solid fa-bomb"></i>',
    '<i class="fa-solid fa-skull"></i>',
    '<i class="fa-solid fa-ghost"></i>',
    '<i class="fa-solid fa-user-secret"></i>',
    '<i class="fa-solid fa-user-ninja"></i>',
    '<i class="fa-solid fa-user-astronaut"></i>',
    '<i class="fa-solid fa-user-tie"></i>',
    '<i class="fa-solid fa-users"></i>',
    '<i class="fa-solid fa-people-group"></i>',
    '<i class="fa-solid fa-child"></i>',
    '<i class="fa-solid fa-baby"></i>',
    '<i class="fa-solid fa-person"></i>',
    '<i class="fa-solid fa-person-dress"></i>',
    '<i class="fa-solid fa-check"></i>',
    '<i class="fa-solid fa-xmark"></i>',
    '<i class="fa-solid fa-plus"></i>',
    '<i class="fa-solid fa-minus"></i>',
    '<i class="fa-solid fa-equals"></i>',
    '<i class="fa-solid fa-divide"></i>',
    '<i class="fa-solid fa-asterisk"></i>',
    '<i class="fa-solid fa-hashtag"></i>',
    '<i class="fa-solid fa-at"></i>',
    '<i class="fa-solid fa-exclamation"></i>',
    '<i class="fa-solid fa-question"></i>',
    '<i class="fa-solid fa-info"></i>',
    '<i class="fa-solid fa-arrow-up"></i>',
    '<i class="fa-solid fa-arrow-down"></i>',
    '<i class="fa-solid fa-arrow-left"></i>',
    '<i class="fa-solid fa-arrow-right"></i>',
    '<i class="fa-solid fa-arrows-rotate"></i>',
    '<i class="fa-solid fa-recycle"></i>',
    '<i class="fa-solid fa-trash"></i>',
    '<i class="fa-solid fa-trash-can"></i>',
    '<i class="fa-solid fa-ban"></i>',
    '<i class="fa-solid fa-circle"></i>',
    '<i class="fa-solid fa-square"></i>',
    '<i class="fa-solid fa-triangle-exclamation"></i>',
    '<i class="fa-solid fa-diamond"></i>',
    '<i class="fa-solid fa-cube"></i>',
    '<i class="fa-solid fa-cubes"></i>',
    '<i class="fa-solid fa-dice-one"></i>',
    '<i class="fa-solid fa-dice-two"></i>',
    '<i class="fa-solid fa-dice-three"></i>',
    '<i class="fa-solid fa-dice-four"></i>',
    '<i class="fa-solid fa-dice-five"></i>',
    '<i class="fa-solid fa-dice-six"></i>'
];

$predefined_colors = [
    // Original Gelb-Töne (dein Theme)
    '#e6a309',
    '#ebad36',
    '#f0b753',
    '#f4c16c',
    '#f8cb85',
    '#fbd59d',

    // Rot-Töne
    '#dc2626',
    '#ef4444',
    '#f87171',
    '#fca5a5',
    '#fecaca',
    '#991b1b',

    // Orange-Töne
    '#c2410c',
    '#ea580c',
    '#f97316',
    '#fb923c',
    '#fdba74',
    '#fed7aa',

    // Gelb-Töne
    '#ca8a04',
    '#eab308',
    '#facc15',
    '#fde047',
    '#fef08a',
    '#fef9c3',

    // Grün-Töne
    '#166534',
    '#16a34a',
    '#22c55e',
    '#4ade80',
    '#86efac',
    '#bbf7d0',

    // Türkis-Töne
    '#0e7490',
    '#0891b2',
    '#06b6d4',
    '#22d3ee',
    '#67e8f9',
    '#a5f3fc',

    // Blau-Töne
    '#1e40af',
    '#2563eb',
    '#3b82f6',
    '#60a5fa',
    '#93c5fd',
    '#bfdbfe',

    // Indigo-Töne
    '#3730a3',
    '#4f46e5',
    '#6366f1',
    '#818cf8',
    '#a5b4fc',
    '#c7d2fe',

    // Violett-Töne
    '#6b21a8',
    '#7c3aed',
    '#8b5cf6',
    '#a78bfa',
    '#c4b5fd',
    '#ddd6fe',

    // Pink-Töne
    '#a21caf',
    '#c026d3',
    '#d946ef',
    '#e879f9',
    '#f0abfc',
    '#fae8ff',

    // Rosa-Töne
    '#be123c',
    '#e11d48',
    '#f43f5e',
    '#fb7185',
    '#fda4af',
    '#fecdd3',

    // Grau-Töne
    '#374151',
    '#4b5563',
    '#6b7280',
    '#9ca3af',
    '#d1d5db',
    '#e5e7eb',

    // Braun-Töne
    '#92400e',
    '#b45309',
    '#d97706',
    '#f59e0b',
    '#fbbf24',
    '#fde68a',

    // Teal-Töne
    '#115e59',
    '#14b8a6',
    '#2dd4bf',
    '#5eead4',
    '#99f6e4',
    '#ccfbf1',

    // Lime-Töne
    '#365314',
    '#65a30d',
    '#84cc16',
    '#a3e635',
    '#bef264',
    '#d9f99d',

    // Cyan-Töne
    '#164e63',
    '#0e7490',
    '#0891b2',
    '#06b6d4',
    '#22d3ee',
    '#67e8f9',

    // Amber-Töne
    '#78350f',
    '#b45309',
    '#d97706',
    '#f59e0b',
    '#fbbf24',
    '#fde68a',

    // Emerald-Töne
    '#064e3b',
    '#047857',
    '#10b981',
    '#34d399',
    '#6ee7b7',
    '#a7f3d0',

    // Sky-Töne
    '#075985',
    '#0284c7',
    '#0ea5e9',
    '#38bdf8',
    '#7dd3fc',
    '#bae6fd',

    // Fuchsia-Töne
    '#86198f',
    '#a21caf',
    '#c026d3',
    '#d946ef',
    '#e879f9',
    '#f0abfc'
];

// Form-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $icon = $_POST['icon'] ?? '';
    $color = $_POST['color'] ?? '';

    $errors = [];

    // Validierung
    if (empty($name)) {
        $errors[] = 'Name ist erforderlich.';
    } elseif (strlen($name) < 2) {
        $errors[] = 'Name muss mindestens 2 Zeichen lang sein.';
    } elseif (strlen($name) > 50) {
        $errors[] = 'Name darf maximal 50 Zeichen lang sein.';
    }

    if (empty($icon)) {
        $errors[] = 'Bitte wähle ein Icon aus.';
    }

    if (empty($color)) {
        $errors[] = 'Bitte wähle eine Farbe aus.';
    } elseif (!preg_match('/^#[0-9a-f]{6}$/i', $color)) {
        $errors[] = 'Ungültiger Farbcode-Format.';
    }

    // Prüfe ob Name bereits existiert (für diesen Benutzer und Typ, außer bei der aktuellen Kategorie)
    if (!empty($name)) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND type = ? AND id != ?");
        $stmt->execute([$name, $category['type'], $category_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Eine andere Kategorie mit diesem Namen existiert bereits für diesen Typ.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE categories 
                SET name = ?, icon = ?, color = ?
                WHERE id = ?
            ");

            $stmt->execute([$name, $icon, $color, $category_id]);

            $_SESSION['success'] = 'Kategorie "' . htmlspecialchars($name) . '" erfolgreich aktualisiert!';
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }

    // Update category data with form values on error
    $category['name'] = $name;
    $category['icon'] = $icon;
    $category['color'] = $color;
}

// Type mapping für Anzeige
$type_mapping = [
    'income' => '<i class="fa-solid fa-sack-dollar"></i> Einnahme',
    'expense' => '<i class="fa-solid fa-money-bill-wave"></i> Ausgabe',
    'debt_in' => '<i class="fa-solid fa-arrow-left"></i> Schuld Eingang',
    'debt_out' => '<i class="fa-solid fa-arrow-right"></i> Schuld Ausgang'
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategorie bearbeiten - Meine Firma Finance</title>
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-..."
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/categories.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a class="sidebar-logo">
                    <img src="../../assets/images/logo.png" alt="Meine Firma Finance Logo" class="sidebar-logo-image">
                </a>
                <p class="sidebar-welcome">Willkommen, <?= htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']) ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="../../dashboard.php"><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard</a></li>
                    <li><a href="../expenses/index.php"><i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben</a></li>
                    <li><a href="../income/index.php"><i class="fa-solid fa-sack-dollar"></i>&nbsp;&nbsp;Einnahmen</a></li>
                    <li><a href="../debts/index.php" class="<?= strpos($_SERVER['PHP_SELF'], 'debts') ? 'active' : '' ?>">
                            <i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden
                        </a></li>
                    <li><a href="../recurring/index.php"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
                    <li><a href="../investments/index.php"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
                    <li><a href="index.php" class="active"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorien</a></li>
                    <li>
                        <a style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;" href="../../settings.php">
                            <i class="fa-solid fa-gear"></i>&nbsp;&nbsp;Einstellungen
                        </a>
                    </li>
                    <li><a href="../settings/license.php"><i class="fas fa-key"></i>&nbsp;&nbsp;Lizenz</a></li>
                    <li>
                        <a href="../../logout.php"><i class="fa-solid fa-right-from-bracket"></i>&nbsp;&nbsp;Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorie bearbeiten</h1>
                    <p style="color: var(--clr-surface-a50);">Aktualisiere die Details deiner Kategorie</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorie bearbeiten</h2>
                        <p>Ändere Name, Icon und Farbe deiner Kategorie</p>
                    </div>

                    <div class="current-info">
                        <h4>📋 Aktuelle Kategorie</h4>
                        <div class="current-category">
                            <div class="current-icon" style="background-color: <?= htmlspecialchars($category['color']) ?>;">
                                <?= $category['icon'] ?>
                            </div>
                            <div class="current-details">
                                <h5><?= htmlspecialchars($category['name']) ?></h5>
                                <span class="current-type <?= $category['type'] ?>">
                                    <?= $type_mapping[$category['type']] ?? $category['type'] ?>
                                </span>
                            </div>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-item">
                                <strong>Transaktionen:</strong><br>
                                <span class="stat-value"><?= $stats['transaction_count'] ?></span>
                            </div>
                            <div class="stat-item">
                                <strong>Gesamtbetrag:</strong><br>
                                <span class="stat-value">€<?= number_format($stats['total_amount'], 2, ',', '.') ?></span>
                            </div>
                            <div class="stat-item">
                                <strong>Erstellt:</strong><br>
                                <span class="stat-value"><?= date('d.m.Y', strtotime($category['created_at'])) ?></span>
                            </div>
                            <div class="stat-item">
                                <strong>Zuletzt verwendet:</strong><br>
                                <span class="stat-value">
                                    <?= $stats['last_used'] ? date('d.m.Y', strtotime($stats['last_used'])) : 'Nie' ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($stats['transaction_count'] > 0): ?>
                            <div class="warning-box">
                                ⚠️ <strong>Hinweis:</strong> Diese Kategorie wird in <?= $stats['transaction_count'] ?> Transaktionen verwendet.
                                Änderungen wirken sich auf alle bestehenden Einträge aus.
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Fehler:</strong><br>
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="name">Name der Kategorie *</label>
                            <input type="text" id="name" name="name"
                                class="form-input"
                                value="<?= htmlspecialchars($category['name']) ?>"
                                placeholder="z.B. Lebensmittel, Gehalt, Miete..."
                                maxlength="50" required
                                oninput="updatePreview()">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Icon auswählen *</label>
                            <div class="icon-selector">
                                <?php foreach ($predefined_icons as $icon): ?>
                                    <div class="icon-option">
                                        <input type="radio" id="icon_<?= md5($icon) ?>" name="icon" value="<?= htmlspecialchars($icon) ?>"
                                            class="icon-radio" <?= $category['icon'] === $icon ? 'checked' : '' ?>
                                            onchange="updatePreview()">
                                        <label for="icon_<?= md5($icon) ?>" class="icon-label">
                                            <?= $icon ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Farbe auswählen *</label>
                            <div class="color-selector">
                                <?php foreach ($predefined_colors as $color): ?>
                                    <div class="color-option">
                                        <input type="radio" id="color_<?= substr($color, 1) ?>" name="color" value="<?= $color ?>"
                                            class="color-radio" <?= $category['color'] === $color ? 'checked' : '' ?>
                                            onchange="updatePreview()">
                                        <label for="color_<?= substr($color, 1) ?>" class="color-label"
                                            style="background-color: <?= $color ?>;"></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="preview-section">
                            <div class="preview-title">🔄 Neue Vorschau</div>
                            <div class="category-preview">
                                <div class="preview-icon" id="previewIcon" style="background-color: <?= htmlspecialchars($category['color']) ?>;">
                                    <?= $category['icon'] ?>
                                </div>
                                <div class="preview-name" id="previewName">
                                    <?= htmlspecialchars($category['name']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                            <?php if ($stats['transaction_count'] == 0): ?>
                                <a href="delete.php?id=<?= $category['id'] ?>" class="btn btn-delete"
                                    onclick="return confirm('Kategorie wirklich löschen?')">
                                    <i class="fa-solid fa-trash-can"></i> Löschen
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn btn-delete" disabled title="Kategorie wird verwendet und kann nicht gelöscht werden">
                                    <i class="fa-solid fa-lock"></i> Wird verwendet
                                </button>
                            <?php endif; ?>
                            <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Änderungen speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        function updatePreview() {
            const name = document.getElementById('name').value || '<?= htmlspecialchars($category['name']) ?>';
            const selectedIcon = document.querySelector('input[name="icon"]:checked');
            const selectedColor = document.querySelector('input[name="color"]:checked');

            document.getElementById('previewName').textContent = name;

            if (selectedIcon) {
                document.getElementById('previewIcon').innerHTML = selectedIcon.value;
            }

            if (selectedColor) {
                document.getElementById('previewIcon').style.backgroundColor = selectedColor.value;
            }
        }

        // Initial preview update
        document.addEventListener('DOMContentLoaded', function() {
            updatePreview();
        });
    </script>
</body>

</html>