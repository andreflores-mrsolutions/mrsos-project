<?php
// Create connection
$conectar = mysqli_connect("localhost", "root", "", "mrsos");
// Check connection
if (!$conectar) {
    die("Connection failed: " . mysqli_connect_error());
}
