<?php
$name=$email=$phone=$password="";
$errors=[];
if($_SERVER["REQUEST_METHOD"]==="POST"){
 $name=trim($_POST["name"]); $email=trim($_POST["email"]); $phone=trim($_POST["phone"]); $password=$_POST["password"];
 if(!preg_match("/^[A-Za-z ]{3,50}$/",$name)) $errors[]="Invalid name";
 if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]="Invalid email";
 if(!preg_match("/^[0-9]{10}$/",$phone)) $errors[]="Invalid phone";
 if(!preg_match("/^.{6,}$/",$password)) $errors[]="Password must contain at least 6 characters";
 if(!$errors) echo "<h1>Registration Successful</h1><p>Welcome, ".htmlspecialchars($name)."</p>";
 else { echo "<h1>Validation Errors</h1><ul>"; foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; echo "</ul>";}
}
?>