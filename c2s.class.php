<?php
//set_time_limit(3);
/*
1.加载json数据
2.获取所有积木块ID
3.获取所有头部积木块（HATs，此类积木块为每组代码的起始位置）
4.从头部积木块开始解析，并从所有积木块数组中，将已解析的积木块索引删除，剩余的积木块，即为零散积木块，在C中暂时可以舍弃，或者独立放置。
      解析时，需要遍历链表。
*/
include_once "expression2rpn.class.php";			//处理四则混合运算的逆波兰类定义

$soup = '!#%()*+,-./:;=?@[]^_`{|}~'.'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

function uid()			//生成20字节的UID(Scratch3.0官方算法)
{
   global $soup;
   $id = Array();
   for ($i = 0; $i < 20; $i++) {
      $id[$i] = $soup[mt_rand(0,86) ];		//87个字符中随机抽一个
   }
   return implode('',$id);
}

/********************************************
*
**  将类C文本转换成Scratch3.0的JSON数据
*
********************************************/
class CToScratch3
{
   private  $rpn_exp	 = NULL;						//处理四则混合运算的逆波兰类对象
   private  $arrCODEDATA = Array(NULL,NULL,NULL,NULL);				//客户端传过来的代码数据
   private  $Blockly	 = Array(Array(),Array());				//代码经解析后生成的积木块数据
   private  $nType	 = 0;							//在对堆栈进行操作时用以区分积木块归属类型：有/无事件触发的积木
   private  $UIDS 	 = NULL;						//用来存储各个积木块间关系的堆栈

   //初始化
   function __construct( $strCODEDATA )
   {
      $this->arrCODEDATA =unserialize($strCODEDATA);				//处理由serialize处理数组后生成的字符串文本，还原成数组

      $this->rpn_exp = new RPN_EXPRESSION();							//处理四则混合运算的逆波兰类进行初始化

      for($i=0;$i<4;$i++)							//拆分数据。0和1是变量，2和3是代码
      {
         if($this->arrCODEDATA[$i]!=NULL)
         {
            if($i>1)										//2和3的脚本需要拆分成若干关键词段
            {
               $old_str=Array('/\\}/', '/\\{/', '/;/', '/\\(/', '/\\)/', '/,/');		//替换：大括号，分号，小括号，逗号，一律在前后都加回车符
               $new_str=Array("\n}\n","\n{\n","\n;\n","\n(\n","\n)\n","\n,\n");
               $this->arrCODEDATA[$i]=preg_replace($old_str,$new_str,$this->arrCODEDATA[$i]);
               $arrTemp=Array();
               $arr=explode("\n",$this->arrCODEDATA[$i]); 					//拆分：按回车符拆分
               //print_r($arr);
               for($n=0;$n<count($arr);$n++)
               {
                  $strCode=trim($arr[$n]);							//过滤空格，空行
                  if($strCode!='' && strstr($strCode,"//")=='')					//过滤空行和注释信息
                  {
                     $arrTemp[]=$strCode;
                  }
               }
               $this->arrCODEDATA[$i]=$arrTemp;
            }
            else										//0和1的变量按分号拆分
               $this->arrCODEDATA[$i]=explode(";",$this->arrCODEDATA[$i]);
         }
      }

      //调试用
      if($this->arrCODEDATA==Array(NULL,NULL,NULL,NULL))
      {
         $this->arrCODEDATA=unserialize(file_get_contents("CODEDATA.txt")); 	//无POST数据，从本地调取数据
      }
      else
      {
         file_put_contents("CODEDATA.txt",serialize($this->arrCODEDATA));		//备份POST过来的数据
      }
      
   }

   //数据处理主入口
   //负责四种类型数据的归并，并调用处理程序对数据进行梳理。
   function deal()
   {
      $arrFuncs = Array(Array(),Array(),Array(),Array());		//存放解析后的数据
      foreach($this->arrCODEDATA as $key=>$arr)
      {
         $arrFunc=Array();
         switch($key)
         {
         case 0:							//适用于所有角色的变量
         case 1:
            if($arr==NULL) break;//{$arrFuncs[$key]=Array(); break;}
            for($i=0;$i<count($arr);$i++)
            {
                preg_match("/int([^^]*?)=([^^]*?);/",$arr[$i].";",$arg);
                if(count($arg)==3)
                {
                   $arrFunc[]=Array(uid(),trim($arg[1]),trim($arg[2]));
                }
            }
            $arrFuncs[$key]=$arrFunc;			//收集数据
            break;

         case 2:							//带有触发事件的积木块
         case 3:
            if($arr==NULL) break;			//数据为空，不处理
            $this->UIDS=Array(NULL,uid());		//parent_uid,this_uid
            $this->nType=($key-2);			//该类型积木放在数组偏移量为0的位置
            $this->getFuncs($arr);			//处理被拆分的代码文本数据，处理完的数据直接放在Blockly[0]里
            break;
         }
      }
      $arrFuncs[2]=$this->Blockly[0];			//将积木放到指定位置
      $arrFuncs[3]=$this->Blockly[1];

      return $arrFuncs;
   }


   //getFuncs主要处理是否包含有子程序的代码段的处理，比如重复执行，循环，条件判断等。
   /*
      UIDS=Array(NULL,uid());	//默认值（parentuid,thisuid）
      [0]      pop(thisuid)  pop(parentuid) push(thisuid) push(thisuid) push(nextuid)  //要进入递归程序，需要多压一个thisuid。这个值，在递归结束时，会被下一个积木作为parentuid使用。
         [0]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
         [1]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
         [2]      pop(thisuid)  pop(parentuid) push(thisuid) push(thisuid) push(nextuid)  //要进入递归程序，需要多压一个thisuid。
            [0]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
            [1]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
            [2]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
                  //清理最后一条数据的next记录
                  pop(nextuid)   pop(thisuid)   push(nextuid)	//递归返回，需要将之前的thisuid删除。递归结束后，由于后续没有积木需要用到最后一个积木的thisuid，所以这个thisuid就作废了。
         [3]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
         [4]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
               //清理最后一条数据的next记录
               pop(nextuid)   pop(thisuid)   push(nextuid)	//递归返回，需要将之前的thisuid删除。
      [1]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
      [2]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
            //清理最后一条数据的next记录。最后一条积木数据没有next数据。但，不处理似乎也是可以的。
            //输出结果
   */
   private function getFuncs($arrCode)
   {
      $acc=count($arrCode);		//文本代码拆分成数组后的长度
      for($i=0;$i<$acc;$i++)
      {
         $opcode=$arrCode[$i];
         switch($arrCode[$i])
         {
            case "event_whenflagclicked":			//Hats参数的解析
            case "event_whenthisspriteclicked":			//Hats参数的解析
               $nCount=0;
               $i+=2;
               $childFunc=Array();
               while($i<$acc)
               {
                  $i++;
                  if($arrCode[$i]=="{") 
                  {
                     $childFunc[]=$arrCode[$i];	$nCount++;
                  }
                  else if($arrCode[$i]=="}") 
                  {
                     $childFunc[]=$arrCode[$i];	$nCount--;
                  }
                  else
                  {
                     $childFunc[]=$arrCode[$i];	//需要返回ID
                  }
                  if($nCount==0)							//计数器回到默认状态，说明这个循环可以结束了。
                  {
                     break;
                  }
               }
               array_shift($childFunc);
               array_pop($childFunc);

               $nextuid=uid();
               $thisuid=array_pop($this->UIDS);		//出栈：this_uid
               $parentuid=array_pop($this->UIDS);		//出栈：parent_uid
               array_push($this->UIDS,$thisuid);		//入栈：this_uid
               array_push($this->UIDS,$thisuid);		//入栈：this_uid	//将进入下一层，需要多压入一次，以便在返回时仍保留一份数据
               array_push($this->UIDS,$nextuid);		//入栈：next_uid
               $this->getFuncs($childFunc);			//递归处理子程序集

               $arrBlockTemp=array_pop($this->Blockly[$this->nType]);	//已返回，需要清掉最后一条数据的nextuid信息，接下来的积木块，parentuid是进入前的积木的thisuid
               if($arrBlockTemp)
               {
                  $j=json_decode($arrBlockTemp);
                  $j->{'next'}=NULL; 
                  array_push($this->Blockly[$this->nType],json_encode($j));
               }
		//Hats积木块的主信息
               array_push($this->Blockly[$this->nType],'{  "id": "'.$thisuid.'",  "opcode": "'.$opcode.'",  "inputs": {},  "fields": {},  "next": "'.$nextuid.'",  "topLevel": true,  "parent": null,  "shadow": false}' );
               $uid=array_pop($this->UIDS);			//出栈：next_uid
               array_pop($this->UIDS);				//出栈：parent_uid	//已返回，需要将上一个积木的thisuid（即当前的parent_uid）删除
               array_push($this->UIDS,$uid);			//入栈：next_uid

            break;

            case "for":							//for循环比hats多了一个循环条件参数的解析。
               $i+=1;							//函数名，小括号，int i=0,
               $strCondition='';
               while($i<$acc)
               {
                  $i++;
                  if($arrCode[$i]=="i++") break;
                  $strCondition.=$arrCode[$i];
               }
               $arrCondition=Array();
               preg_match("/int ([^^]*?)=([^^]*?);([^^]*?)<([^^]*?);/",$strCondition,$m);
               if(count($m)==5)
               {
                  $strCondition=trim($m[4])."-".trim($m[2]);
                  if(trim($m[2])=='0')   $strCondition=trim($m[4]);
                  $this->rpn_exp->init($strCondition);
                  $arrCondition=$this->rpn_exp->toScratchJSON();
               }
               //if($arrCondition==NULL)
               //   $arrCondition[0]=$strCondition;		//FOR循环条件，这里还需要处理一下

               $nCount=0;
               $childFunc=Array();
               $i+=1;
               while($i<$acc)
               {
                  $i++;
                  if($arrCode[$i]=="{")
                  {
                     $childFunc[]=$arrCode[$i];	$nCount++;
                  }
                  else if($arrCode[$i]=="}")
                  {
                     $childFunc[]=$arrCode[$i];	$nCount--;
                  }
                  else
                  {
                     $childFunc[]=$arrCode[$i];	//需要返回ID
                  }
                  if($nCount==0)			//计数器回到默认状态，说明这个循环可以结束了。
                  {
                     break;
                  }
               }
               array_shift($childFunc);
               array_pop($childFunc);

               $nextuid=uid();					//规则同event
               $thisuid=array_pop($this->UIDS);
               $parentuid=array_pop($this->UIDS);
               array_push($this->UIDS,$thisuid);
               array_push($this->UIDS,$thisuid);
               array_push($this->UIDS,$nextuid);

               $this->getFuncs($childFunc);			//递归调用处理子程序集

               $arrBlockTemp=array_pop($this->Blockly[$this->nType]);
               if($arrBlockTemp)// && isset($arrBlockTemp["next"]))
               {
                  $j=json_decode($arrBlockTemp);
                  $j->{'next'}=NULL;                  				//清掉了最后一个的nextuid
                  array_push($this->Blockly[$this->nType],json_encode($j));
               }

               $childuid1=uid();
               				//重复执行n次的参数设置
               array_push($this->Blockly[$this->nType],	'{ "id": "'.$childuid1.'", "opcode": "math_whole_number", "inputs": {}, "fields": { "NUM": { "name": "NUM", "value": "'.$strCondition.'" } }, "next": null, "topLevel": false, "parent": "'.$thisuid.'", "shadow": true}' );
               				//重复执行n次的主信息
               array_push($this->Blockly[$this->nType],'{ "id": "'.$thisuid.'", "opcode": "control_repeat", "inputs": { "TIMES": { "name": "TIMES", "block": "'.$childuid1.'", "shadow": "'.$childuid1.'" }, "SUBSTACK": { "name": "SUBSTACK", "block": "'.$nextuid.'", "shadow": null } }, "fields": {}, "next": {}, "topLevel": '.($parentuid==NULL?'true':'false').', "parent": "'.$parentuid.'", "shadow": false}' );

               $uid=array_pop($this->UIDS);
               array_pop($this->UIDS);
               array_push($this->UIDS,$uid);

            break;

            default:							//其它以“;”结尾的普通函数调用的解析
               $childFunc=Array();
               while( $i<$acc && $arrCode[$i]!=";")				//这里是对整个函数的剥离，所以不用考虑参数的多少。
               {
                  $childFunc[]=$arrCode[$i];
                  $i++;
               }
               if(isset($arrCode[$i]))
                  $childFunc[]=$arrCode[$i];					//最后一个“;”一定要加上，否则parseArg里无法识别。

               $this->parseArg($childFunc);			//其它标准函数，都在parseArg里处理
 
            break;
         }
      }
   }


/**********************************************************
*
**  拆分解析函数的参数（如果参数是公式，需要调用RPN来处理）
*
***********************************************************/
   private function parseArg( $arrFunc)
   {
      if(!isset($arrFunc[0])) return NULL;

//print_r($arrFunc);
      $nextuid=uid();
      $thisuid=array_pop($this->UIDS);
      $parentuid=array_pop($this->UIDS);

      array_push($this->UIDS,$thisuid);
      array_push($this->UIDS,$nextuid);

      switch($arrFunc[0])
      {
         //主调函数的处理方法
         //格式：funName(arg);


      	 /***********************带参数函数，需要处理inputs和fields*************************/
         //运动
         case "motion_movesteps":			//移动n步
         case "motion_turnright":			//向右转
         case "motion_turnleft":			//向左转
         case "motion_changexby":			//将X坐标增加n
         case "motion_changeyby":			//将Y坐标增加n
         case "motion_setx":				//将X坐标设为
         case "motion_sety":				//将Y坐标设为
         case "motion_pointindirection":		//面向n°方向
      				//多参数函数
         case "motion_glidesecstoxy":		//n秒内滑行到xy
         case "motion_gotoxy":			//将Y坐标设为
         //外观
         case "looks_say":
         case "looks_changesizeby":
         case "looks_setsizeto":

         //声音

         //事件

         //控制
         case "control_wait":
         //case "control_repeat":		//这个单独在getFuncs里处理。

         //侦测

         //运算

         //变量

         //自制积木

         //画笔
         case "pen_setPenColorToColor":
         case "pen_changePenColorParamBy":
         //音乐

             //扫描多个参数的分隔符“,”，再进行两参数的拆分
             /*********************************************/
            $tmpExpression=Array("","","");					//标准积木，最多三个参数，因为需要执行字符追加，所以得初始化。
            $nExpressionCount=0;
            for($i=2;$i<count($arrFunc)-2;$i++)
            {
               if($arrFunc[$i]==',') $nExpressionCount++;			//有逗号，就表示是多个参数
               else $tmpExpression[$nExpressionCount].=$arrFunc[$i];
            }

            $argArr=Array();							//这里直接根据偏移量赋值，所以可以不用像$tmpExpression那样进行初始化。
            for($i=0;$i<=$nExpressionCount;$i++)
            {
               if(!is_numeric($tmpExpression[$i]))				//非纯数字的参数，利用RPN算法进行分解。
               {
                  $this->rpn_exp -> init($tmpExpression[$i]);				//参数中存在的空格问题，在rpn中处理
                  $arg=$this->rpn_exp->toScratchJSON();
                  if($arg==FALSE) $argArr[$i]=$this->rpn_exp->getStrRPN();		//公式
                  else
                     $argArr[$i]=$arg;						//值
               }
               else $argArr[$i]=trim($tmpExpression[$i]);			//纯数字参数，注意去除空格。
            }

            $childuid1=uid();
            $childuid2=uid();
            $childuid3=uid();//最多有三个参数

            $arrChildArg=$this->getArgName($arrFunc[0]);		//获取参数的名称
            $nCAC=count($arrChildArg);					//获取参数的个数

            //补上参数设置
            if($nCAC>0)							//至少有一个参数
            {
               array_push($this->Blockly[$this->nType],	'{"id": "'.$childuid1.'", "opcode": "'.$arrChildArg[0][1].'", "inputs": {}, "fields": { "'.$arrChildArg[0][2].'": { "name": "'.$arrChildArg[0][2].'", "value": '.$argArr[0].' } }, "next": null, "topLevel": false, "parent": "'.$thisuid.'", "shadow": true}' );
            }

            if($nCAC>1)							//存在第二个参数
            {
               array_push($this->Blockly[$this->nType],	'{"id": "'.$childuid2.'", "opcode": "'.$arrChildArg[1][1].'", "inputs": {}, "fields": { "'.$arrChildArg[1][2].'": { "name": "'.$arrChildArg[1][2].'", "value": '.$argArr[1].' } }, "next": null, "topLevel": false, "parent": "'.$thisuid.'", "shadow": true}' );
            }

            if($nCAC>2)							//存在第三个参数
            {
               array_push($this->Blockly[$this->nType],	'{"id": "'.$childuid3.'", "opcode": "'.$arrChildArg[2][1].'", "inputs": {}, "fields": { "'.$arrChildArg[2][2].'": { "name": "'.$arrChildArg[2][2].'", "value": '.$argArr[2].' } }, "next": null, "topLevel": false, "parent": "'.$thisuid.'", "shadow": true}' );
            }

            //积木块的主参数
            array_push($this->Blockly[$this->nType],'{"id": "'.$thisuid.'", "opcode": "'.$arrFunc[0].'", "inputs": {'.
               (
                 ($nCAC==3)?			//三个参数
                    ('"'.$arrChildArg[0][0].'": { "name": "'.$arrChildArg[0][0].'", "block": "'.$childuid1.'", "shadow": "'.$childuid1.'" }, "'.$arrChildArg[1][0].'": { "name": "'.$arrChildArg[1][0].'", "block": "'.$childuid2.'", "shadow": "'.$childuid2.'" }, "'.$arrChildArg[2][0].'": { "name": "'.$arrChildArg[2][0].'", "block": "'.$childuid3.'", "shadow": "'.$childuid3.'" }')
                  :
                    (
                       ($nCAC==2)?		//两个参数
                            ('"'.$arrChildArg[0][0].'": { "name": "'.$arrChildArg[0][0].'", "block": "'.$childuid1.'", "shadow": "'.$childuid1.'" }, "'.$arrChildArg[1][0].'": { "name": "'.$arrChildArg[1][0].'", "block": "'.$childuid2.'", "shadow": "'.$childuid2.'" }')
                          :			//否则一个参数
                            ('"'.$arrChildArg[0][0].'": { "name": "'.$arrChildArg[0][0].'", "block": "'.$childuid1.'", "shadow": "'.$childuid1.'" }')
                     )
               )
               .'}, "fields": {}, "next": "'.$nextuid.'", "topLevel": '.($parentuid==NULL?'true':'false').', "parent": '.($parentuid==NULL?"null":"\"".$parentuid."\"").', "shadow": false}');

         break;


         //无参数积木，不需要inputs和fields
         //运动
         case "motion_ifonedgebounce":		//遇到边缘就反弹
         //外观
         case "looks_show":			//显示
         case "looks_hide":			//隐藏
         case "looks_cleargraphiceffects":	//清除图像特效
         case "looks_nextcostume":		//下一个造型
         case "looks_nextbackdrop":		//下一个背景
         //声音
         case "sound_stopallsounds":		//停止所有声音
         case "sound_cleareffects":		//清除音效
         //事件
         //控制
         case "control_delete_this_clone":	//删除此克隆体
         //侦测
         case "sensing_resettimer":		//计时器归零
         //运算
         //变量
         //自制积木
         //画笔
         case "pen_stamp":
         case "pen_penDown":
         case "pen_penUp":
         //音乐

            //构建积木数据
            array_push($this->Blockly[$this->nType],'{ "id": "'.$thisuid.'", "opcode": "'.$arrFunc[0].'", "inputs": {}, "fields": {}, "next": "'.$nextuid.'", "topLevel": '.($parentuid==NULL?'true':'false').', "parent": '.($parentuid==NULL?"{}":"\"".$parentuid."\"").', "shadow": false}' );
         break;

         default:
            return NULL;
      }
   }

   //根据opcode，获取对应的所有参数的名字
   /*******************************************
   Array(Array("inputs的第一个child的name","inputs的第一个child的block对应的数据的opcode","inputs的第一个child的block对应的数据的fields的name"),
         Array("inputs的第二个child的name","inputs的第二个child的block对应的数据的opcode","inputs的第二个child的block对应的数据的fields的name"),
         Array("inputs的第三个child的name","inputs的第三个child的block对应的数据的opcode","inputs的第三个child的block对应的数据的fields的name"));

Blocks._blocks;
{
    "ID1": {
        "id": "ID1",
        "opcode": "pen_changePenColorParamBy",
        "inputs": {
            "COLOR_PARAM": {
                "name": "COLOR_PARAM",			//inputs的第一个child的name
                "block": "ID2",
                "shadow": "ID2"
            },
            "VALUE": {
                "name": "VALUE",			//inputs的第二个child的name
                "block": "ID3",
                "shadow": "ID3"
            }
        },
        "fields": {},
        "next": null,
        "topLevel": true,
        "parent": null,
        "shadow": false
    },
    "ID2": {
        "id": "ID2",
        "opcode": "pen_menu_colorParam",		//inputs的第一个child的block对应的数据的opcode
        "inputs": {},
        "fields": {
            "colorParam": {
                "name": "colorParam",			//inputs的第一个child的block对应的数据的fields的name
                "value": "color"
            }
        },
        "next": null,
        "topLevel": false,
        "parent": "ID1",
        "shadow": true
    },
    "ID3": {
        "id": "ID3",
        "opcode": "math_number",			//inputs的第二个child的block对应的数据的opcode
        "inputs": {},
        "fields": {
            "NUM": {
                "name": "NUM",				//inputs的第一个child的block对应的数据的fields的name
                "value": "10"
            }
        },
        "next": null,
        "topLevel": false,
        "parent": "ID1",
        "shadow": true
    }
}


   *******************************************/
   private function getArgName($opcode)
   {
      $arrArgName=Array(
         //运动
         "motion_movesteps"=>Array(Array("STEPS","math_number","NUM")),
         "motion_changexby"=>Array(Array("DX","math_number","NUM")),
         "motion_changeyby"=>Array(Array("DY","math_number","NUM")),
         "motion_setx"=>Array(Array("X","math_number","NUM")),
         "motion_sety"=>Array(Array("Y","math_number","NUM")),
         "motion_gotoxy"=>Array(Array("X","math_number","NUM"),Array("Y","math_number","NUM")),
         "motion_glidesecstoxy"=>Array(Array("SECS","math_number","NUM"),Array("X","math_number","NUM"),Array("Y","math_number","NUM")),
         "motion_turnright"=>Array(Array("DEGREES","math_number","NUM")),
         "motion_turnleft"=>Array(Array("DEGREES","math_number","NUM")),
         "motion_pointindirection"=>Array(Array("DIRECTION","math_number","NUM")),

         //外观
         "looks_say"=>Array(Array("MESSAGE","math_number","TEXT")),
         "looks_changesizeby"=>Array(Array("CHANGE","math_number","NUM")),
         "looks_setsizeto"=>Array(Array("SIZE","math_number","NUM")),

         //声音

         //事件

         //控制
         "control_wait"=>Array(Array("DURATION","math_number","NUM")),
         //"control_repeat"=>Array(Array("SUBSTACK","math_number","NUM"),Array("TIMES","math_number","NUM")),//这个已经转成for了，在getFuncs里处理。

         //侦测

         //运算

         //变量

         //自制积木

         //画笔
         "pen_setPenColorToColor"=>Array(Array("COLOR","colour_picker","COLOUR")),
         "pen_changePenColorParamBy"=>Array(Array("COLOR_PARAM","pen_menu_colorParam","colorParam"),Array("VALUE","math_number","NUM")),

         //音乐

      );
      return isset($arrArgName[$opcode])?$arrArgName[$opcode]:Array();
   }
}
?>
