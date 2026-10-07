<?php
$xml=simplexml_load_file("products.xml") or die("Unable to load XML");
?>
<!DOCTYPE html><html><body><h1>Products from XML</h1><table border="1" cellpadding="8">
<tr><th>ID</th><th>Name</th><th>Price</th></tr>
<?php foreach($xml->product as $p): ?>
<tr><td><?=htmlspecialchars($p['id'])?></td><td><?=htmlspecialchars($p->name)?></td><td><?=htmlspecialchars($p->price)?></td></tr>
<?php endforeach; ?>
</table></body></html>