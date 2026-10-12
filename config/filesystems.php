<?php
return ['default'=>'public','disks'=>['public'=>['driver'=>'local','root'=>storage_path('app/public'),'url'=>env('APP_URL').'/storage','visibility'=>'public','throw'=>true]],'links'=>[public_path('storage')=>storage_path('app/public')]];
