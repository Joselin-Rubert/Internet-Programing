<?php include "db.php"; $result=$conn->query("SELECT * FROM orders");
echo "<h1>Online Orders</h1><table border='1' cellpadding='8'><tr><th>ID</th><th>Customer</th><th>Product</th><th>Quantity</th></tr>";
while($row=$result->fetch_assoc()) echo "<tr><td>{$row['id']}</td><td>".htmlspecialchars($row['customer'])."</td><td>".htmlspecialchars($row['product'])."</td><td>{$row['quantity']}</td></tr>";
echo "</table>"; $conn->close(); ?>