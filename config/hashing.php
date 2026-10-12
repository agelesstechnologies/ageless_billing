<?php
return ['driver'=>'bcrypt','bcrypt'=>['rounds'=>(int)env('BCRYPT_ROUNDS',12),'verify'=>true,'limit'=>null],'rehash_on_login'=>true];
