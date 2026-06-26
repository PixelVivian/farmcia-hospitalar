<?php
/**
 * controllers/logout_controller.php
 * Encerra a sessão do usuário e retorna para a tela de login.
 */
session_start();
session_unset();
session_destroy();
header('Location: ../views/login.php');
exit;
