<?php

$usuarios = ["admin@gmail.com", "juan@gmail.com"];
$passwords = ["1234", "abcd"];

$email = $_POST["email"];
$password = $_POST["password"];

$valido = false;

for ($i = 0; $i < count($usuarios); $i++) {
    if ($email == $usuarios[$i] && $password == $passwords[$i]) {
        $valido = true;
        break;
    }
}

if ($valido) {
    echo "success";
} else {
    echo "error";
}

?>