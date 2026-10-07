<?php include "db.php";
$customer=$_POST["customer"]; $product=$_POST["product"]; $quantity=(int)$_POST["quantity"];
$stmt=$conn->prepare("INSERT INTO orders(customer,product,quantity) VALUES(?,?,?)");
$stmt->bind_param("ssi",$customer,$product,$quantity); $stmt->execute();
echo "<h1>Order Saved</h1><a href='orders.php'>View Orders</a>"; $stmt->close(); $conn->close();
?>