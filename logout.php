<?php  if (!isset($_SESSION)) { session_start(); } ?>

<?php 
include_once 'i18n.inc.php';
include 'header.inc.php'; 
include_once "constants.php";

unset($_SESSION['userid']);
unset($_SESSION['username']);
unset($_SESSION['realname']);
session_destroy();
          
echo ("<script> parent.location.replace('../codev'); </script>");
       
?>
     
<?php include 'footer.inc.php'; ?>
