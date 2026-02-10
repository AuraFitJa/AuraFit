<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
echo "<h1>Dashboard Professionista</h1>";
echo "<p>Utente: " . htmlspecialchars($_SESSION['email']) . "</p>";
echo "<p>Ruoli: " . htmlspecialchars(implode(", ", $_SESSION['roles'] ?? [])) . "</p>";
echo '<p><a href="logout.php">Logout</a></p>';
