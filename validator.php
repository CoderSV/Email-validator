<?php


/**
 * Example 1
 * Validate a single Email via SMTP
 */

if($_POST['email'] != ''){
// the email to validate  
$email = $_POST['email'];  

require('smtp-validate-email.php');

$sender  = 'info@svgamestore.com'; // for SMTP FROM:<> command


// do the validation  
$result =  validateEmailSmtp($email, $sender, true);  
// view results  

echo var_dump($result);;  
  
// send email?   
if ($result) {  
  //mail(...);  
}

}
?>



<form method="POST">
<input type="text" name="email">
<input type="submit" value="submit">
</form>


