# rocksOauth
rocksOauth v1.0

install using Composer: ```composer require josephblock/rocksoauth```

Example in examples/login.php


```php
//set up rocksOauth
$r=new rocksOauth();
/*
 * Change values to what you need
 * scopes are set as constants
*/
$r->setClient("Your client here");
$r->setSecret("Your secret here");
$r->addScope(array("Add scopes here"));
$r->setRedirect("redirect URL here");
```
