<?php
//set_time_limit(3);
/*
1.加载json数据
2.获取所有积木块ID
3.获取所有头部积木块（HATs，此类积木块为每组代码的起始位置）
4.从头部积木块开始解析，并从所有积木块数组中，将已解析的积木块索引删除；
5.剩余的积木块，即为零散积木块，按照同样的方式进行数据转换。

使用方法：
   include "s2c.class.php";

   $d=file_get_contents("./sprite.json");	//.SB3文件中的project.json里的部分数据
   $scratch= new Scratch3ToC($d);		//初始化
   $scratch->compileSB3();			//转换编译
   $scratch->dumpCodeInC();			//输出结果
   file_put_contents("sc.txt",serialize($scratch->codeInC));	//数组结果写入文件

*/


class Scratch3ToC
{
   private $Blocks;					//原始的Scratch3.0项目脚本的JSON数据
   private $Variables;
   private $codeInC=Array(Array(),Array(),Array(),Array());			//转换后的伪代码，数据按先后顺序，以字符的方式存放于数组中。
   private $nLeftPadding=0;				//代码对齐补空格
   private $currentType=0;
   private $arrProcedureName=Array();			//改名后的自制积木名称
   private $hats=Array(					//Hat类型的积木块
      "event_whenflagclicked"=>1,			//更新：
      "event_whenkeypressed"=>1,			//    将value改成了key，
      "event_whenthisspriteclicked"=>1,			//    通过isset来检测，
      "event_whenbackdropswitchesto"=>1,		//    以规避in_array的速度风险。
      "event_whengreaterthan"=>1,
      "event_whenbroadcastreceived"=>1,
      "control_start_as_clone"=>1,
      "event_whenstageclicked"=>1
   );

   private $arrBlockID=NULL;				//积木清单
   //  Array(
   //     "BLOCKID1"=>0,
   //  );
   

   //初始化，将传入的字符串转成JSON数据格式
   function __construct($Blocks,$Variables)
   {
//var_dump( json_decode( $Blocks ));
      $this->Blocks  = json_decode( $Blocks );
//print_r($this->Blocks);

      $this->Variables  = json_decode( $Variables );
//print_r($this->Variables);
   }

   //检测某opcode是否为hat类型
   function checkHat($opcode)
   {
      return isset($this->hats[$opcode]);					//更新：将in_array改成了isset
   }

   //填充缩进的空格字符
   function padding()
   {
      return str_pad("",$this->nLeftPadding*3," ",STR_PAD_LEFT);
   }

   //将积木代码转成伪代码
   //转换的数据被依次保存在数组$codeInC中。
   function convertCode($BlockID)
   {
      //echo "CURRENT:\t".$BlockID."\n";
      if($BlockID=='') return;
      //print_r($this->Blocks->{$BlockID});
      $Block=isset($this->Blocks->{$BlockID})?$this->Blocks->{$BlockID}:''; 	//此处可能会出现不存在现象，需要研究是否跟新添变量有关。
						//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
						//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
						//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
						//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
						//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

      if(isset($this->arrBlockID[$Block->{"id"}]))
         unset($this->arrBlockID[$Block->{"id"}]);				//对于存在的积木，需从清单中清除，并执行后续的转换操作；
      else return;								//对于不存在的积木，则直接返回。

      switch($Block->{"opcode"})					//根据opcode来确认应如何转换
      {
         /**************************EVENT事件**************************/
         //把每个事件，都封装成类似于函数重载的方式
         //Scratch中允许同一个角色有多个同名的Event，这个在现有的其它编程语言中是不被允许的，
         //所以，这里转换得到的代码，只能是伪代码，或者另起炉灶，新创一个语言？

         case "event_whenflagclicked":
            $this->codeInC[$this->currentType][]= "//当绿旗被点击\n";
            $this->codeInC[$this->currentType][]= $Block->{"opcode"}."(){\n";
            $this->nLeftPadding++;
            break;

         case "event_whenkeypressed":
            $this->codeInC[$this->currentType][]= "//当某键被按下\n".$Block->{"opcode"}."(\"";
            $this->codeInC[$this->currentType][]= $Block->{"fields"}->{"KEY_OPTION"}->{"value"};
            $this->codeInC[$this->currentType][]= "\"){\n";
            $this->nLeftPadding++;
            break;

         case "event_whenthisspriteclicked":
            $this->codeInC[$this->currentType][]= "//当角色被点击\n".$Block->{"opcode"}."(){\n";
            $this->nLeftPadding++;
            break;

         case "event_whenbroadcastreceived":
            $this->codeInC[$this->currentType][]= "//当接收到广播\n".$Block->{"opcode"}."(\"";
            $this->codeInC[$this->currentType][]= $Block->{"fields"}->{"BROADCAST_OPTION"}->{"value"};
            $this->codeInC[$this->currentType][]= "\"){\n";
            $this->nLeftPadding++;
            break;

         case "chattingroom_whenChatMessageComes":
            //var_dump($Block);
            $this->codeInC[$this->currentType][]= "//当接收到广播\n".$Block->{"opcode"}."(){\n";
            $this->nLeftPadding++;
            break;



         /**************************计算**************************/
         //计算要解决变量的定义，以及层层嵌套的问题。

         case "operator_lt":			//小于
            //var_dump($Block);
            $this->codeInC[$this->currentType][]=" ( ";
            $this->convertCode($Block->{"inputs"}->{"OPERAND1"}->{"block"});
            $this->codeInC[$this->currentType][]=" < ";
            $this->convertCode($Block->{"inputs"}->{"OPERAND2"}->{"block"});
            $this->codeInC[$this->currentType][]=" ) ";
            break;

         case "operator_gt":			//大于
            $this->codeInC[$this->currentType][]=" ( ";
            $this->convertCode($Block->{"inputs"}->{"OPERAND1"}->{"block"});
            $this->codeInC[$this->currentType][]=" > ";
            $this->convertCode($Block->{"inputs"}->{"OPERAND2"}->{"block"});
            $this->codeInC[$this->currentType][]=" ) ";
            break;

         case "operator_equals":		//等于
            $this->codeInC[$this->currentType][]=" ( ";
            $this->convertCode($Block->{"inputs"}->{"OPERAND1"}->{"block"});
            $this->codeInC[$this->currentType][]=" == ";
            $this->convertCode($Block->{"inputs"}->{"OPERAND2"}->{"block"});
            $this->codeInC[$this->currentType][]=" ) ";
            break;

         case "operator_add":			//加法
            $this->codeInC[$this->currentType][]=" ( ";
            $this->convertCode($Block->{"inputs"}->{"NUM1"}->{"block"});
            $this->codeInC[$this->currentType][]=" + ";
            $this->convertCode($Block->{"inputs"}->{"NUM2"}->{"block"});
            $this->codeInC[$this->currentType][]=" ) ";
            break;

         case "operator_subtract":		//减法
            $this->codeInC[$this->currentType][]=" ( ";
            $this->convertCode($Block->{"inputs"}->{"NUM1"}->{"block"});
            $this->codeInC[$this->currentType][]=" - ";
            $this->convertCode($Block->{"inputs"}->{"NUM2"}->{"block"});
            $this->codeInC[$this->currentType][]=" ) ";
            break;

         case "operator_multiply":		//乘法
            $this->codeInC[$this->currentType][]=" ( ";
            $this->convertCode($Block->{"inputs"}->{"NUM1"}->{"block"});
            $this->codeInC[$this->currentType][]=" * ";
            $this->convertCode($Block->{"inputs"}->{"NUM2"}->{"block"});
            $this->codeInC[$this->currentType][]=" ) ";
            break;

         case "operator_divide":		//除法
            $this->codeInC[$this->currentType][]=" ( ";
            $this->convertCode($Block->{"inputs"}->{"NUM1"}->{"block"});
            $this->codeInC[$this->currentType][]=" / ";
            $this->convertCode($Block->{"inputs"}->{"NUM2"}->{"block"});
            $this->codeInC[$this->currentType][]=" ) ";
            break;

         case "operator_mod":			//求余
            $this->codeInC[$this->currentType][]=" ( ";
            $this->convertCode($Block->{"inputs"}->{"NUM1"}->{"block"});
            $this->codeInC[$this->currentType][]=" % ";
            $this->convertCode($Block->{"inputs"}->{"NUM2"}->{"block"});
            $this->codeInC[$this->currentType][]=" ) ";
            break;

         case "operator_and":			//且
            $this->codeInC[$this->currentType][]=" ( ";
            $this->convertCode($Block->{"inputs"}->{"OPERAND1"}->{"block"});
            $this->codeInC[$this->currentType][]=" && ";
            $this->convertCode($Block->{"inputs"}->{"OPERAND2"}->{"block"});
            $this->codeInC[$this->currentType][]=" ) ";
            break;

         case "operator_or":			//或
            $this->codeInC[$this->currentType][]=" ( ";
            $this->convertCode($Block->{"inputs"}->{"OPERAND1"}->{"block"});
            $this->codeInC[$this->currentType][]=" || ";
            $this->convertCode($Block->{"inputs"}->{"OPERAND2"}->{"block"});
            $this->codeInC[$this->currentType][]=" ) ";
            break;

         case "operator_not":			//非
            $this->codeInC[$this->currentType][]=" ! ";
            $this->convertCode($Block->{"inputs"}->{"OPERAND"}->{"block"});
            $this->codeInC[$this->currentType][]="  ";
            break;

         case "operator_mathop":		//函数
            if($Block->{"fields"}->{"OPERATOR"}->{"value"}=="10 ^")			//10的n次方
            {
               $this->codeInC[$this->currentType][]=" pow( 10 ,";
               $this->convertCode($Block->{"inputs"}->{"NUM"}->{"block"});
               $this->codeInC[$this->currentType][]=" ) ";
            }
            else if($Block->{"fields"}->{"OPERATOR"}->{"value"}=="e ^")			//e的n次方
            {
               $this->codeInC[$this->currentType][]=" pow( E ,";
               $this->convertCode($Block->{"inputs"}->{"NUM"}->{"block"});
               $this->codeInC[$this->currentType][]=" ) ";
            }
            else
            {
               $this->codeInC[$this->currentType][]=$Block->{"fields"}->{"OPERATOR"}->{"value"};		//函数名
               $this->codeInC[$this->currentType][]="( ";
               $this->convertCode($Block->{"inputs"}->{"NUM"}->{"block"});
               $this->codeInC[$this->currentType][]=" ) ";
            }
            break;

         case "operator_random":		//随机数
            //print_r($Block);
            $this->codeInC[$this->currentType][]=" rand( ";
            $this->convertCode($Block->{"inputs"}->{"FROM"}->{"block"});
            $this->codeInC[$this->currentType][]=" , ";
            $this->convertCode($Block->{"inputs"}->{"TO"}->{"block"});
            $this->codeInC[$this->currentType][]=" ) ";
            break;

         case "operator_length":		//字符串长度
            //print_r($Block);
            $this->codeInC[$this->currentType][]=" strlen( ";
            $this->convertCode($Block->{"inputs"}->{"STRING"}->{"block"});
            $this->codeInC[$this->currentType][]=" ) ";
            break;

         case "operator_letter_of":		//列表下标取值
            //print_r($Block);
            //$this->codeInC[$this->currentType][]="list_";
            $this->convertCode($Block->{"inputs"}->{"STRING"}->{"block"});
            $this->codeInC[$this->currentType][]="[ ";
            $this->convertCode($Block->{"inputs"}->{"LETTER"}->{"block"});
            $this->codeInC[$this->currentType][]=" ] ";
            break;

         /**************************CONTROL控制**************************/
         //控制里也需要处理嵌套问题。

         case "control_wait":			//等待n秒
            //var_dump($Block);
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."(";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"DURATION"}->{"block"});
            $this->codeInC[$this->currentType][]= ");\n";
            break;

         case "control_repeat":			//重复执行n次
            $this->codeInC[$this->currentType][]= $this->padding()."for(int i = 0; i < ";
            $this->convertCode( $Block->{"inputs"}->{"TIMES"}->{"block"});
            $this->codeInC[$this->currentType][]= "; i++ ){\n";
            $this->nLeftPadding++;
            if(isset($Block->{"inputs"}->{"SUBSTACK"}) && $Block->{"inputs"}->{"SUBSTACK"}->{"block"}!=NULL)			//检测包含的子积木块
            {
               $this->convertFromRest($Block->{"inputs"}->{"SUBSTACK"}->{"block"});
            }
            else
            {
                $this->nLeftPadding--;
                $this->codeInC[$this->currentType][]=$this->padding()."}\n";
            }
            break;

         case "control_forever":		//重复执行
            $this->codeInC[$this->currentType][]= $this->padding()."do{\n";

            $this->nLeftPadding++;
            if(isset($Block->{"inputs"}->{"SUBSTACK"}) && $Block->{"inputs"}->{"SUBSTACK"}->{"block"}!=NULL)			//检测包含的子积木块
            {
               $this->convertFromRest($Block->{"inputs"}->{"SUBSTACK"}->{"block"});
            }
            else
            {
                $this->nLeftPadding--;
                $this->codeInC[$this->currentType][]=$this->padding()."}";
            }

            $this->codeInC[$this->currentType][]= $this->padding()."while (1);\n";
            break;

         case "control_repeat_until":		//重复执行直到
            $this->codeInC[$this->currentType][]= $this->padding()."do{\n";
            $this->nLeftPadding++;
            if(isset($Block->{"inputs"}->{"SUBSTACK"}) && $Block->{"inputs"}->{"SUBSTACK"}->{"block"}!=NULL)			//检测包含的子积木块
            {
               $this->convertFromRest($Block->{"inputs"}->{"SUBSTACK"}->{"block"});
            }
            else
            {
                $this->nLeftPadding--;
                $this->codeInC[$this->currentType][]=$this->padding()."}";
            }

            $this->codeInC[$this->currentType][]= $this->padding()."while( !";
            $this->convertCode( $Block->{"inputs"}->{"CONDITION"}->{"block"});
            $this->codeInC[$this->currentType][]= ");\n";
            break;

         case "control_if":			//如果那么
            $this->codeInC[$this->currentType][]= $this->padding()."if( ";

            if(isset($Block->{"inputs"}->{"CONDITION"}->{"block"}))
            {
               $this->convertCode( $Block->{"inputs"}->{"CONDITION"}->{"block"});
            }
            $this->codeInC[$this->currentType][]= " ){\n";
            $this->nLeftPadding++;
            if(isset($Block->{"inputs"}->{"SUBSTACK"}) && $Block->{"inputs"}->{"SUBSTACK"}->{"block"}!=NULL)			//检测包含的子积木块
            {
               $this->convertFromRest($Block->{"inputs"}->{"SUBSTACK"}->{"block"});
            }
            else
            {
                $this->nLeftPadding--;
                $this->codeInC[$this->currentType][]=$this->padding()."}\n";
            }
            break;

         case "control_if_else":		//如果那么否则
            $this->codeInC[$this->currentType][]= $this->padding()."if( ";
            if(isset($Block->{"inputs"}->{"CONDITION"}->{"block"}))
            {
               $this->convertCode( $Block->{"inputs"}->{"CONDITION"}->{"block"});
            }
            $this->codeInC[$this->currentType][]= " ){\n";
            $this->nLeftPadding++;
            if(isset($Block->{"inputs"}->{"SUBSTACK"}) && $Block->{"inputs"}->{"SUBSTACK"}->{"block"}!=NULL)			//检测包含的子积木块
            {
               $this->convertFromRest($Block->{"inputs"}->{"SUBSTACK"}->{"block"});
            }
            else
            {
                $this->nLeftPadding--;
                $this->codeInC[$this->currentType][]=$this->padding()."}\n";
            }
            $this->codeInC[$this->currentType][]= $this->padding()."else{\n";
            $this->nLeftPadding++;
            if(isset($Block->{"inputs"}->{"SUBSTACK"}) && $Block->{"inputs"}->{"SUBSTACK2"}->{"block"}!=NULL)			//检测包含的子积木块
            {
               $this->convertFromRest($Block->{"inputs"}->{"SUBSTACK2"}->{"block"});
            }
            else
            {
                $this->nLeftPadding--;
                $this->codeInC[$this->currentType][]=$this->padding()."}\n";
            }
            break;

         case "control_wait_until":		//等待直到
            $this->codeInC[$this->currentType][]= $this->padding()."do{}\n";
            $this->codeInC[$this->currentType][]= $this->padding()."while (!";
            $this->convertCode( $Block->{"inputs"}->{"CONDITION"}->{"block"});
            $this->codeInC[$this->currentType][]= ");\n";
            break;

         case "control_stop":			//停止
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( \"";
            $this->codeInC[$this->currentType][]= $Block->{"fields"}->{"STOP_OPTION"}->{"value"};
            $this->codeInC[$this->currentType][]= "\" );\n";
            break;


         /**************************MOTION运动**************************/
         //这里要处理变量问题。

         case "motion_movesteps":		//移动n步
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"STEPS"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "motion_turnright":		//右转n度
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"DEGREES"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "motion_turnleft":		//左转n度
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"DEGREES"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "motion_gotoxy":			//移到xy
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"X"}->{"block"});
            $this->codeInC[$this->currentType][]= " , ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"Y"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "motion_pointindirection":	//移到“随机位置/鼠标指针”
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"DIRECTION"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "motion_pointtowards":		//面向n度方向
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( \"";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"TOWARDS"}->{"block"});
            $this->codeInC[$this->currentType][]= "\" );\n";
            break;

         case "motion_changexby":		//将x坐标增加
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"DX"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "motion_changeyby":		//将y坐标增加
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"DY"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "motion_setx":			//将x坐标设为
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"X"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "motion_sety":			//将y坐标设为
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"Y"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "looks_show":			//显示
         case "looks_hide":			//隐藏
         case "looks_cleargraphiceffects":	//清除图像特效
         case "sound_stopallsounds":		//停止所有声音
         case "sound_cleareffects":		//清除音效
         case "sensing_resettimer":		//计时器归零
         case "control_delete_this_clone":	//删除此克隆体
         case "looks_nextcostume":		//下一个造型
         case "looks_nextbackdrop":		//下一个背景
         case "motion_ifonedgebounce":		//碰到边缘就反弹
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."();\n";
            break;

         case "sensing_timer":
            //print_r($Block);
            $this->codeInC[$this->currentType][]  = "sensing_timer() ";
            //$this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"MESSAGE"}->{"block"});
            //$this->codeInC[$this->currentType][]= " ,";
            //$this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"SECS"}->{"block"});
            //$this->codeInC[$this->currentType][]= " );\n";
            break;

         case "sensing_coloristouchingcolor":
            //print_r($Block);
            $this->codeInC[$this->currentType][]  = $Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"COLOR"}->{"block"});
            $this->codeInC[$this->currentType][]= " ,";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"COLOR2"}->{"block"});
            $this->codeInC[$this->currentType][]= " )";
            break;

         case "sensing_touchingcolor":
            //print_r($Block);
            $this->codeInC[$this->currentType][]  = $Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"COLOR"}->{"block"});
            //$this->codeInC[$this->currentType][]= " ,";
            //$this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"COLOR2"}->{"block"});
            $this->codeInC[$this->currentType][]= " )";
            break;

         case "colour_picker":
            //print_r($Block);
            $this->codeInC[$this->currentType][]  = "\"".$Block->{"fields"}->{"COLOUR"}->{"value"}."\"";
            //$this->codeInC[$this->currentType][]= "\"".$this->convertCode($Block->{"fields"}->{"COLOUR"}->{"value"})."\"";
            break;

         case "event_broadcast":
            //print_r($Block);
            $this->codeInC[$this->currentType][]  = $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"BROADCAST_INPUT"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "event_broadcast_menu":
            //print_r($Block);
            $this->codeInC[$this->currentType][]  = "\"".$Block->{"fields"}->{"BROADCAST_OPTION"}->{"value"}."\"";
            break;

         case "operator_join":
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"STRING1"}->{"block"});
            $this->codeInC[$this->currentType][]= " ,";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"STRING2"}->{"block"});
            $this->codeInC[$this->currentType][]= " )\n";
         break;

         case "chattingroom_sendMsgTo":
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"USER"}->{"block"});
            $this->codeInC[$this->currentType][]= " ,";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"MSG"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
         break;

         case "chattingroom_menu_userlist":
            //print_r($Block);
            $this->codeInC[$this->currentType][]  = "\"".$Block->{"fields"}->{"userlist"}->{"value"}."\"";
            break;

         case "chattingroom_lastReceivedMsg":
            //print_r($Block);
            $this->codeInC[$this->currentType][]  = $Block->{"opcode"}."()";
            break;



         case "looks_sayforsecs":			//说n秒
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"MESSAGE"}->{"block"});
            $this->codeInC[$this->currentType][]= " ,";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"SECS"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "looks_say":				//说
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"MESSAGE"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "looks_thinkforsecs":			//想n秒
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"MESSAGE"}->{"block"});
            $this->codeInC[$this->currentType][]= " ,";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"SECS"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "looks_think":				//想
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"MESSAGE"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "looks_switchcostumeto":				//换成造型
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $costumeBlockID=$Block->{"inputs"}->{"COSTUME"}->{"block"}==''?$Block->{"inputs"}->{"COSTUME"}->{"shadow"}:$Block->{"inputs"}->{"COSTUME"}->{"block"};
            $Block2=$this->Blocks->{$costumeBlockID} ;

            if(isset($Block2->{"fields"}->{"COSTUME"}))//->{"vlaue"}))
               $this->codeInC[$this->currentType][]= "\"".$Block2->{"fields"}->{"COSTUME"}->{"value"}."\"";
            else
               $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"COSTUME"}->{"block"});

            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "looks_costume":				//switchcostumeto的默认shadow值，直接在switchcostumeto里处理了，不需要单独处理。
            //print_r($Block);
//            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            //$this->codeInC[$this->currentType][]= "\"".$Block->{"fields"}->{"COSTUME"}->{"value"}."\"";
//            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "looks_switchbackdropto":				//换成背景
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $backdropBlockID=$Block->{"inputs"}->{"BACKDROP"}->{"block"}==''?$Block->{"inputs"}->{"BACKDROP"}->{"shadow"}:$Block->{"inputs"}->{"BACKDROP"}->{"block"};
            $Block2=$this->Blocks->{$backdropBlockID} ;

            if(isset($Block2->{"fields"}->{"BACKDROP"}))//->{"vlaue"}))
               $this->codeInC[$this->currentType][]= "\"".$Block2->{"fields"}->{"BACKDROP"}->{"value"}."\"";
            else
               $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"BACKDROP"}->{"block"});

            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "looks_backdrops":				//switchbackdropto的默认shadow值，直接在switchbackdropto里处理了，不需要单独处理。
            //print_r($Block);
//            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            //$this->codeInC[$this->currentType][]= "\"".$Block->{"fields"}->{"COSTUME"}->{"value"}."\"";
//            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "looks_changesizeby":				//将大小增加
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"CHANGE"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "looks_setsizeto":				//将大小设为
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"SIZE"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "looks_changeeffectby":				//将特效增加
            //print_r($Block);
            
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= "\"".$Block->{"fields"}->{"EFFECT"}->{"value"}."\"";
            $this->codeInC[$this->currentType][]= " , ";


            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"CHANGE"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "looks_seteffectto":				//将特效设为
            //print_r($Block);
            
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= "\"".$Block->{"fields"}->{"EFFECT"}->{"value"}."\"";
            $this->codeInC[$this->currentType][]= " , ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"VALUE"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;


         case "looks_gotofrontback":				//至于顶端
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= "\"".$Block->{"fields"}->{"FRONT_BACK"}->{"value"}."\"";
            $this->codeInC[$this->currentType][]= " );\n";
            break;


         case "looks_goforwardbackwardlayers":			//上移/下移n层
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= "\"".$Block->{"fields"}->{"FORWARD_BACKWARD"}->{"value"}."\"";
            $this->codeInC[$this->currentType][]= ",";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"NUM"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;



         case "motion_setrotationstyle":			//将旋转方式设为“左右翻转/不可旋转/任意旋转”
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( \"";
            $this->codeInC[$this->currentType][]= $Block->{"fields"}->{"STYLE"}->{"value"};
            $this->codeInC[$this->currentType][]= "\" );\n";
            break;

         case "motion_glidesecstoxy":				//n秒内滑行到xy
            $this->codeInC[$this->currentType][]= $this->padding().$Block->{"opcode"}."( ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"SECS"}->{"block"});
            $this->codeInC[$this->currentType][]= " , ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"X"}->{"block"});
            $this->codeInC[$this->currentType][]= " , ";
            $this->codeInC[$this->currentType][]= $this->convertCode($Block->{"inputs"}->{"Y"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         /**************************MATH运算**************************/

         case "text":				//大于小于等于中的普通文本参数
            if($Block->{"parent"}!=NULL)//运算积木原本就带数字的，一旦被其它积木代替，就不起作用了。这类数字，它的parent为NULL。
              $this->codeInC[$this->currentType][]= is_numeric($Block->{"fields"}->{"TEXT"}->{"value"})?$Block->{"fields"}->{"TEXT"}->{"value"}:"\"".$Block->{"fields"}->{"TEXT"}->{"value"}."\"";
            break;

         case "math_number":			//加减乘除中的普通数字参数
            if($Block->{"parent"}!=NULL)//运算积木原本就带数字的，一旦被其它积木代替，就不起作用了。这类数字，它的parent为NULL。
               $this->codeInC[$this->currentType][]= $Block->{"fields"}->{"NUM"}->{"value"};
            break;

         case "math_angle":			//旋转角度的普通数字参数
            if($Block->{"parent"}!=NULL)//运算积木原本就带数字的，一旦被其它积木代替，就不起作用了。这类数字，它的parent为NULL。
               $this->codeInC[$this->currentType][]= $Block->{"fields"}->{"NUM"}->{"value"};
            break;

         case "math_integer":			//前移1层
            if($Block->{"parent"}!=NULL)//运算积木原本就带数字的，一旦被其它积木代替，就不起作用了。这类数字，它的parent为NULL。
               $this->codeInC[$this->currentType][]= $Block->{"fields"}->{"NUM"}->{"value"};
            break;

         case "math_whole_number"://整数。重复执行n次里的数据
            if($Block->{"parent"}!=NULL)//运算积木原本就带数字的，一旦被其它积木代替，就不起作用了。这类数字，它的parent为NULL。
               $this->codeInC[$this->currentType][]= $Block->{"fields"}->{"NUM"}->{"value"};
            break;

         case "math_positive_number"://正数
            if($Block->{"parent"}!=NULL)//运算积木原本就带数字的，一旦被其它积木代替，就不起作用了。这类数字，它的parent为NULL。
               $this->codeInC[$this->currentType][]= $Block->{"fields"}->{"NUM"}->{"value"};
            break;

         case "argument_reporter_string_number"://自制积木参数
            //print_r($Block);
            if($Block->{"parent"}!=NULL)//运算积木原本就带数字的，一旦被其它积木代替，就不起作用了。这类数字，它的parent为NULL。
               $this->codeInC[$this->currentType][]= $Block->{"fields"}->{"VALUE"}->{"value"};
            break;

         /**************************变量**************************/

            /**************************变量**************************/
         case "data_variable"://变量
            if($Block->{"parent"}!=NULL)//运算积木原本就带数字的，一旦被其它积木代替，就不起作用了。这类数字，它的parent为NULL。
               $this->codeInC[$this->currentType][]= "var_".$Block->{"fields"}->{"VARIABLE"}->{"value"};
            break;

         case "data_setvariableto"://将变量设为
            //var_dump($Block);
            //if($Block->{"parent"}!=NULL)
            //{
               $this->codeInC[$this->currentType][]= $this->padding().'var_'.$Block->{"fields"}->{"VARIABLE"}->{"value"};
               $this->codeInC[$this->currentType][]=" = ";
               $this->convertCode($Block->{"inputs"}->{"VALUE"}->{"block"});
               $this->codeInC[$this->currentType][]= " ;\n";
            //}
            break;

         case "data_changevariableby"://将变量增加
            //if($Block->{"parent"}!=NULL)
            //{
               $this->codeInC[$this->currentType][]= $this->padding()."var_".$Block->{"fields"}->{"VARIABLE"}->{"value"};
               $this->codeInC[$this->currentType][]=" += ";
               $this->convertCode($Block->{"inputs"}->{"VALUE"}->{"block"});
               $this->codeInC[$this->currentType][]= " ;\n";
            //}
            break;

            /**************************列表**************************/

         case "data_addtolist"://列表中增加值
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding()."list_".$Block->{"fields"}->{"LIST"}->{"value"};
            $this->codeInC[$this->currentType][]=".push( ";
            $this->convertCode($Block->{"inputs"}->{"ITEM"}->{"block"});
            $this->codeInC[$this->currentType][]= " );\n";
            break;

         case "data_deleteoflist"://删除列表内某个数据
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding()."list_".$Block->{"fields"}->{"LIST"}->{"value"};
            $this->codeInC[$this->currentType][]=".delete(";
            $this->convertCode($Block->{"inputs"}->{"INDEX"}->{"block"});
            $this->codeInC[$this->currentType][]= ");\n";
            break;

         case "data_deletealloflist"://清空列表数据
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding()."list_".$Block->{"fields"}->{"LIST"}->{"value"};
            $this->codeInC[$this->currentType][]=".removeAll(";
            //$this->convertCode($Block->{"inputs"}->{"ITEM"}->{"block"});
            $this->codeInC[$this->currentType][]= ");\n";
            break;

         case "data_insertatlist"://往列表某项前中插入数据
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding()."list_".$Block->{"fields"}->{"LIST"}->{"value"};
            $this->codeInC[$this->currentType][]=".insert(";
            $this->convertCode($Block->{"inputs"}->{"INDEX"}->{"block"});
            $this->codeInC[$this->currentType][]=",";
            $this->convertCode($Block->{"inputs"}->{"ITEM"}->{"block"});
            $this->codeInC[$this->currentType][]= ");\n";
            break;

         case "data_replaceitemoflist"://替换列表中某项数据
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding()."list_".$Block->{"fields"}->{"LIST"}->{"value"};
            $this->codeInC[$this->currentType][]=".repalce(";
            $this->convertCode($Block->{"inputs"}->{"INDEX"}->{"block"});
            $this->codeInC[$this->currentType][]=",";
            $this->convertCode($Block->{"inputs"}->{"ITEM"}->{"block"});
            $this->codeInC[$this->currentType][]= ");\n";
            break;

         case "data_itemoflist"://取列表中某项的值
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding()."list_".$Block->{"fields"}->{"LIST"}->{"value"};
            $this->codeInC[$this->currentType][]=".getAt(";
            $this->convertCode($Block->{"inputs"}->{"INDEX"}->{"block"});
            $this->codeInC[$this->currentType][]=")";
            break;

         case "data_itemnumoflist"://列表中某数据第一次出现的编号
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding()."list_".$Block->{"fields"}->{"LIST"}->{"value"};
            $this->codeInC[$this->currentType][]=".indexOf(";
            $this->convertCode($Block->{"inputs"}->{"ITEM"}->{"block"});
            $this->codeInC[$this->currentType][]=")";
            break;

         case "data_lengthoflist"://取列表项目数
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding()."list_".$Block->{"fields"}->{"LIST"}->{"value"};
            $this->codeInC[$this->currentType][]=".length()";
            break;

         case "data_listcontainsitem"://列表中是否包含某数
            //print_r($Block);
            $this->codeInC[$this->currentType][]= $this->padding()."list_".$Block->{"fields"}->{"LIST"}->{"value"};
            $this->codeInC[$this->currentType][]=".exist(";
            $this->convertCode($Block->{"inputs"}->{"ITEM"}->{"block"});
            $this->codeInC[$this->currentType][]=")";
            break;


         /**************************自制积木**************************/
         //case "procedures_prototype"://自制积木原形
         //   //print_r($Block);
         //   $this->codeInC[$this->currentType][]= $this->padding()."void ".$Block->{"mutation"}->{"proccode"}."( ";
         //   foreach( $Block->{'inputs'} as $key=>$arr)
         //   {
         //      if($arr->{'block'}!='')
         //      {
         //         $this->convertCode($arr->{"block"});
         //         $this->codeInC[$this->currentType][]= " , ";
         //      }
         //   }
         //   if( $this->codeInC[$this->currentType][count( $this->codeInC[$this->currentType])-1]==" , " )
         //      $this->codeInC[$this->currentType][count( $this->codeInC[$this->currentType])-1]=' ';

         //   $this->codeInC[$this->currentType][]= "){\n";								//差别在这里：这个是函数定义
         //   break;

         case "procedures_call"://过程调用
            //print_r($Block);

            //$prototypeBlockID=$Block->{"inputs"}->{"custom_block"}->{'block'};

            //$Block=$this->Blocks->{$prototypeBlockID}  ;
            //print_r($arrProcedureName);
            $strProcedName=isset($this->arrProcedureName[$Block->{"mutation"}->{"proccode"}])?
				$this->arrProcedureName[$Block->{"mutation"}->{"proccode"}]:
				$Block->{"mutation"}->{"proccode"};

            //echo $strProcedName;
            //$strProcedName=$arrProcedureName[$Block->{"mutation"}->{"proccode"}];

            //$this->codeInC[$this->currentType][]= $this->padding()."void ".$strProcedName."( ";
            //foreach( $Block->{'inputs'} as $key=>$arr)
            //{
            //   if($arr->{'block'}!='')
            //   {
            //      $this->convertCode($arr->{"block"});
            //      $this->codeInC[$this->currentType][]= " , ";
            //   }
            //}




            $this->codeInC[$this->currentType][]= $this->padding()."".$strProcedName."( ";
            foreach( $Block->{'inputs'} as $key=>$arr)
            {
               if($arr->{'block'}!='')
               {
                  $this->convertCode($arr->{"block"});
                  $this->codeInC[$this->currentType][]= " , ";
               }
            }
            if( $this->codeInC[$this->currentType][count( $this->codeInC[$this->currentType])-1]==" , " )
               $this->codeInC[$this->currentType][count( $this->codeInC[$this->currentType])-1]=' ';

            $this->codeInC[$this->currentType][]= ");\n";								//差别在这里：这个是函数调用
            break;

         case "procedures_definition"://自制积木定义
            //print_r($Block);
            //$this->codeInC[$this->currentType][]= $this->padding()."void ".$Block->{"inputs"}->{"custom_block"}->{'name'}."{\n";
            //$this->convertCode($Block->{"inputs"}->{"custom_block"}->{'block'});
            $prototypeBlockID=$Block->{"inputs"}->{"custom_block"}->{'block'};
            $Block=$this->Blocks->{$prototypeBlockID}  ;
            $arguments=json_decode($Block->{"mutation"}->{"argumentnames"});
            $strProcedName = vsprintf($Block->{"mutation"}->{"proccode"},$arguments);
            $strProcedName = str_replace(" ","",$strProcedName);
            $this->arrProcedureName[$Block->{"mutation"}->{"proccode"}]=$strProcedName;

            $this->codeInC[$this->currentType][]= $this->padding()."void ".$strProcedName."( ";
            foreach( $Block->{'inputs'} as $key=>$arr)
            {
               if($arr->{'block'}!='')
               {
                  $this->convertCode($arr->{"block"});
                  $this->codeInC[$this->currentType][]= " , ";
               }
            }
            //print_r($Block);
            if( $this->codeInC[$this->currentType][count( $this->codeInC[$this->currentType])-1]==" , " )
               $this->codeInC[$this->currentType][count( $this->codeInC[$this->currentType])-1]=' ';

            $this->codeInC[$this->currentType][]= "){\n";								//差别在这里：这个是函数定义

            $this->nLeftPadding++;											//控制括号

            //$this->codeInC[$this->currentType][]= "};\n";								//这个地方不需要添加括号
            break;

         //case "procedures_prototype"://自制积木调用
         //   print_r($Block);
         //   $this->codeInC[$this->currentType][]= $this->padding()."void ".$Block->{"inputs"}->{"custom_block"}->{'name'}."{";

         //   $this->convertCode($Block->{"inputs"}->{"custom_block"}->{'block'});

         //   $this->codeInC[$this->currentType][]= "}\n";
         //   break;


         /**************************未定义**************************/

         default:
            $this->codeInC[$this->currentType][]=  $this->padding()."//此功能暂未实现：".$Block->{"opcode"}."\n";
            break;
      }
   }


   //处理需要带括号的积木块，比如Hat类，循环类，判断类
   function convertFromHat($BlockID)
   {
      $this->convertCode($BlockID);					//转换指令，需要传入BlockID
      if($this->Blocks->{$BlockID}->{'next'}!=NULL)
      {
         $this->convertFromHat($this->Blocks->{$BlockID}->{'next'});//获取下一块积木，需要传入BlockID
      }
      else
      {
         $this->nLeftPadding--;
         if($this->nLeftPadding>-1)
            $this->codeInC[$this->currentType][]= $this->padding()."}\n\n";			//该类积木处理完后，需要补一个括号和两个换行
      }
   }

   //处理孤立的积木块
   function convertFromRest($BlockID)
   {
      $this->convertCode($BlockID);					//转换指令，需要传入BlockID
      if($this->Blocks->{$BlockID}->{'next'}!=NULL)
      {
         $this->convertFromRest($this->Blocks->{$BlockID}->{'next'});//获取下一块积木，需要传入BlockID
      }
      else
      {
         if($this->nLeftPadding>0)
         {
            $this->nLeftPadding--;					//因为没有头部积木，所以可能会一直减下去，所以要先判断是否需要减
            $this->codeInC[$this->currentType][]= $this->padding()."}\n";			//该类积木处理完后，需要补一个括号
         }
         else
            $this->codeInC[$this->currentType][]= $this->padding()."\n";				//该类积木处理完后，需要补一个换行
      }
   }

   //获取所有Hat类型的Block的ID
   function getHatBlocks()
   {
      $arrHatBlocks=Array();
      foreach($this->Blocks as $BlockID=>$Block)
      {
         $this->arrBlockID[$BlockID]=0 ;			//0为未处理的Block      1为已处理
         
         if($this->checkHat($Block->{'opcode'})==true)		//获取所有Hats
         {
            $arrHatBlocks[]=$BlockID;
         }
      }
      return $arrHatBlocks;
   }

   //获取所有游离的Block的ID
   function getRestParentBlocks()
   {
      $arrParentBlocks=Array();
      if($this->arrBlockID!=NULL)
      {
         $arrRestBlockID=$this->arrBlockID;

         foreach($arrRestBlockID as $BlockID=>$value)
         {
            if($this->Blocks->{$BlockID}->{'parent'}==NULL) $arrParentBlocks[]=$BlockID;
         }
      }
      return $arrParentBlocks;
   }

   //编译SB3项目文件
   function compileSB3()
   {
      $this->currentType=0;
      if( isset($this->Variables->{"GV"}) && count((array)$this->Variables->{"GV"})>0)
      {
         $this->codeInC[$this->currentType][] = "//适用于所有角色的变量\n";

         foreach($this->Variables->{"GV"} as $VID=>$arr)							//列表还需要单独处理一下。
         {
             //print_r($arr);
             if($arr->{"type"}!="list")
                $this->codeInC[$this->currentType][]="int var_".$arr->{"name"}." = ".$arr->{"value"}.";\n";
             else
                $this->codeInC[$this->currentType][]="int list_".$arr->{"name"}." = [];\n";
         }
         //$this->codeInC[$this->currentType][]="\n";
      }

      $this->currentType=1;
      if( isset($this->Variables->{"CV"}) && count((array)$this->Variables->{"CV"})>0)
      {
         $this->codeInC[$this->currentType][] = "//仅适用于当前角色的变量\n";

         foreach($this->Variables->{"CV"} as $VID=>$arr)
         {
             //print_r($arr);
             if($arr->{"type"}!="list")
                $this->codeInC[$this->currentType][]="int var_".$arr->{"name"}." = ".$arr->{"value"}.";\n";
             else
                $this->codeInC[$this->currentType][]="int list_".$arr->{"name"}." = [];\n";
         }
         //$this->codeInC[$this->currentType][]="\n";
      }


      $this->currentType=2;
      $arrHatBlockID=$this->getHatBlocks();			//头部积木块
      if(count($arrHatBlockID)>0)
      {
         $this->codeInC[$this->currentType][]= "//以下为已关联事件的积木\n";
         for($i=0;$i<count($arrHatBlockID);$i++)
         {
            $this->convertFromHat($arrHatBlockID[$i]);
         }
         //$this->codeInC[$this->currentType][]="\n";
      }

      $this->currentType=3;
      $arrRestBlockID=$this->getRestParentBlocks();		//剩余零散积木块
      if(count($arrRestBlockID)>0)
      {
         $this->codeInC[$this->currentType][]= "//以下为游离的积木\n";

         for($i=0;$i<count($arrRestBlockID);$i++)
         {
            $this->convertFromRest($arrRestBlockID[$i]);
         }
         //$this->codeInC[$this->currentType][]="\n";
      }
   }

   //输出转换结果
   function dumpCodeInC()
   {
      if($this->codeInC!=NULL)
      {
         echo json_encode( Array(implode("",$this->codeInC[0]),implode("",$this->codeInC[1]),implode("",$this->codeInC[2]),implode("",$this->codeInC[3])));
      }
   }
}
?>
