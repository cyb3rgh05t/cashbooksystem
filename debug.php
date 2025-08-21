<?php
require_once 'config/database.php';

$db = new Database();
$pdo = $db->getConnection();

echo "<h2>Kategorien-Debugging</h2>";

$stmt = $pdo->prepare("SELECT id, name, icon, color, type FROM categories WHERE type = 'income'");
$stmt->execute();
$categories = $stmt->fetchAll();

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Name</th><th>Icon</th><th>Color</th><th>Type</th></tr>";

foreach ($categories as $cat) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($cat['id']) . "</td>";
    echo "<td>" . htmlspecialchars($cat['name']) . "</td>";
    echo "<td>" . htmlspecialchars($cat['icon']) . "</td>";
    echo "<td>" . htmlspecialchars($cat['color']) . "</td>";
    echo "<td>" . htmlspecialchars($cat['type']) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>Korrektur-SQL:</h3>";
echo "<pre>";
echo "UPDATE categories SET name = 'Lines' WHERE name LIKE '%data-color%' AND type = 'income';\n";
echo "UPDATE categories SET icon = '&lt;i class=\"fa-solid fa-sack-dollar\"&gt;&lt;/i&gt;' WHERE name = 'Lines';\n";
echo "UPDATE categories SET color = '#22c55e' WHERE name = 'Lines';\n";
echo "</pre>";
