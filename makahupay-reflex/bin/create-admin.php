#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/bootstrap.php';
function prompt(string $label): string { fwrite(STDOUT,$label); return trim((string)fgets(STDIN)); }
function prompt_password(string $label): string {
    fwrite(STDOUT,$label); $hidden=false;
    if (DIRECTORY_SEPARATOR==='/' && function_exists('shell_exec')) { @shell_exec('stty -echo'); $hidden=true; }
    $value=trim((string)fgets(STDIN)); if($hidden)@shell_exec('stty echo'); fwrite(STDOUT,"\n"); return $value;
}
$name=prompt('Administrator full name: ');$email=strtolower(prompt('Administrator email: '));
if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"A name and valid email are required.\n");exit(1);}
$password=prompt_password('Password (minimum 12 characters): ');
if(strlen($password)<12||$password!==prompt_password('Confirm password: ')){fwrite(STDERR,"Passwords must match and be at least 12 characters.\n");exit(1);}
$q=db()->prepare('SELECT id FROM users WHERE email=?');$q->execute([$email]);if($q->fetchColumn()){fwrite(STDERR,"That email already has an account. Edit it in the application instead.\n");exit(1);}
db()->prepare("INSERT INTO users (role_id,name,email,password_hash,status) VALUES (1,?,?,?,'active')")->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
fwrite(STDOUT,"Administrator created successfully: {$email}\n");
