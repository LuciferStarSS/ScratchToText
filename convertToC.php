<?php
set_time_limit(2);
error_reporting(E_ALL);
   $d=isset($_POST["D"])?$_POST["D"]:"";
   $v=isset($_POST["V"])?$_POST["V"]:"";
   //if($d)
   //{
   //   file_put_contents("in_raw2c_d.txt",$d);
   //   file_put_contents("in_raw2c_v.txt",$v);
   //}
   //else
   //{
   //   $d=file_get_contents("in_raw2c_d.txt");
   //   $v=file_get_contents("in_raw2c_v.txt");
   //}

//print_r(json_decode($d));
//print_r(json_decode($v));

//exit;
//echo $d."\r\n\r\n";
//echo $v;
      include "s2c.class.php";
      $scratch= new Scratch3ToC($d,$v);
      $scratch->compileSB3();
      $scratch->dumpCodeInC();

      //$j=json_decode( $d );					//调试用
      //file_put_contents("in_json2c.txt",print_r($j,true));	//调试用


?>