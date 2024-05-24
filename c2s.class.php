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

define("DEBUGs", true);

/********************************************
*
**  将类C文本转换成Scratch3.0的JSON数据
*
********************************************/
class CToScratch3
{
   private  $rpn_exp	 = NULL;						//处理四则混合运算的逆波兰类对象
   private  $arrCODEDATA = Array(NULL,NULL,NULL);				//客户端传过来的代码数据
   private  $Blockly	 = Array();				//代码经解析后生成的积木块数据
   private  $nType	 = 0;							//在对堆栈进行操作时用以区分积木块归属类型：有/无事件触发的积木
   private  $UIDS 	 = NULL;						//用来存储各个积木块间关系的堆栈
   private  $bTOP	 = true;
   private  $bTOPLEVEL   = "true";
   //初始化，拆分文本数据。
   /*
Array
(
    [0] => Array				//适用于所有角色的变量
        (
            [0] => VAR VAR_我的变量 = 0
        )

    [1] => Array				//仅适用于当前角色的变量
        (
            [0] => VAR VAR_i = 0
        )

    [2] => Array				//积木块
        (
            [0] => Array
                (
                    [0] => event_whenflagclicked	//以HAT类型开始的积木块段
                    [1] => (
                    [2] => )
                    [3] => {
                    [4] => motion_movesteps
                    [5] => (
                    [6] => 10
                    [7] => )
                    [8] => ;
                    [9] => motion_turnright
                    [10] => (
                    [11] => 15
                    [12] => )
                    [13] => ;
                    [14] => }				//用“{}”来区分
                )

            [2] => Array
                (
                    [0] => motion_gotoxy		//无HAT类型开始的积木块段
                    [1] => (
                    [2] => 0
                    [3] => ,
                    [4] => 0
                    [5] => )
                    [6] => ;
                    [7] => motion_pointindirection
                    [8] => (
                    [9] => 90
                    [10] => )
                    [11] => ;				//用多个空行来区分
                )

            [3] => Array
                (
                    [0] => motion_gotoxy		//无HAT类型开始的积木块段
                    [1] => (
                    [2] => 10
                    [3] => ,
                    [4] => 10
                    [5] => )
                    [6] => ;
                    [7] => motion_pointindirection
                    [8] => (
                    [9] => 180
                    [10] => )
                    [11] => ;				//用多个空行来区分
                )
        )

)
   */

   //HATS
   private $isHATS=Array(						//Hat类型的积木块。头部积木必须在这里注册，否则会不显示。
      "event_whenflagclicked"			=>2,			//更新：
      "event_whenkeypressed"			=>2,			//    将value改成了key，
      "event_whenthisspriteclicked"		=>2,			//    通过isset来检测，
      "event_whenbackdropswitchesto"		=>2,			//    以规避in_array的速度风险。
      "event_whengreaterthan"			=>2,
      "event_whenbroadcastreceived"		=>2,
      "control_start_as_clone"			=>2,
      "event_whenstageclicked"			=>2,
      "chattingroom_whenChatMessageComes"	=>2,			//当为2时，需要屏蔽{}内的所有连续空行，当}结束时，代码段结束
      "for"					=>1,			//当为1时，需要屏蔽{}内的所有连续空行，当}结束后，按连续空行来拆分；2覆盖1。
      "do"					=>0,			//当为1时，需要屏蔽{}内的所有连续空行，当}结束后，按连续空行来拆分；2覆盖1。
      //"while"					=>1,			//当为1时，需要屏蔽{}内的所有连续空行，当}结束后，按连续空行来拆分；2覆盖1。
      "if"					=>1,			//当为1时，需要屏蔽{}内的所有连续空行，当}结束后，按连续空行来拆分；2覆盖1。
      "else"					=>1,			//当为1时，需要屏蔽{}内的所有连续空行，当}结束后，按连续空行来拆分；2覆盖1。

      //"void"                                    =>2,			//不用在这里处理了。
									//不存在时，按连续空行来拆分
   );

//遇到HAT，必须以完整的{}配对才能结束
//遇到HAT，nSEP如果已经有代码，则开启新的nSEP++
//无HAT时，遇到{}，在{}配对结束前，不根据连续回车分割代码
//如无上述情况，则以连续回车分割代码



   //类初始化
   /*********************************************************
      初始化用于解析表达式的RPN类
      将传入的序列化后的文本反序列化，还原成数组
      对数组中的文本进一步拆分：
         变量仅剩下：类型，变量名，等于号，值
         脚本只剩下：函数名，（），{}，参数
         且，脚本还按照代码段的概念，进行了拆分。
   *********************************************************/
   function __construct( $strCODEDATA )
   {
      $this->rpn_exp     = new RPN_EXPRESSION();			//处理四则混合运算的逆波兰类进行初始化
      $this->arrCODEDATA = unserialize($strCODEDATA);			//处理由serialize处理数组后生成的字符串文本，还原成数组

      for($i=0;$i<3;$i++)						//拆分数据。0和1是变量，2是代码
      {
         if($this->arrCODEDATA[$i]!=NULL)
         {
            if($i<2)							//拆分适用于所有角色和仅适用于当前角色的变量。            		
            {
               $old_str=Array('/\/\*([^^]*?)\*\//','/\/\/([^^]*?)\n/','/\n/');			//注释直接删除
               $new_str=Array("","","");
               $this->arrCODEDATA[$i]=preg_replace($old_str,$new_str,$this->arrCODEDATA[$i]);	//变量按分号拆分
               $this->arrCODEDATA[$i]=array_filter(explode(";",$this->arrCODEDATA[$i]));
            }
            else 							//拆分脚本数据
            {
               $nSEP			= -1;				//游离积木代码段编号
               $bHATSFoundInRest	= false;			//代码段中发现HATS
               $nHATSFoundCounter	= -1;				//HAT计数器
               $RNCounter		= 0;				//空行回车计数器

               $arrCodeSection=Array();  				//带HATS的代码段，与用多回车分隔的代码段，统一解析在一个数组里。   
               $nBreakStatus=0;       

                           //大括号  大括号   分号    小括号  小括号   逗号   注释                注释
               $old_str=Array('/\\}/', '/\\{/', '/;/', '/\\(/', '/\\)/', '/,/','/\/\*([^^]*?)\*\//','/\/\/([^^]*?)\n/');
               $new_str=Array("\n}\n","\n{\n","\n;\n","\n(\n","\n)\n","\n,\n","","");
               $this->arrCODEDATA[2]=preg_replace($old_str,$new_str,$this->arrCODEDATA[2]);	//无关数据过滤

               $arrTemp=explode("\n",$this->arrCODEDATA[2]); 					//按回车符拆分

               //var_dump($arrTemp);
               $nHATSFound		= -1;
               $nBraceFound		= -1;
               $bWaitingforWhile	= false;
               for($n=0;$n<count($arrTemp);$n++)			//遍历处理所有数据
               {
                  $strCode=trim($arrTemp[$n]);					//过滤空格，空行


                  $strCodeArr=explode(" ",$strCode);			//自制积木的检测
                  if(count($strCodeArr)>1)
                  {
                     if($strCodeArr[0]=="void")				//自制积木
                     {
                        $nHATSFound = 2;				//只保留高权限的操作
                        $nSEP++;
                        $arrCodeSection[$nSEP][] = $strCode;		//装配数据
                     }

                  }
                  else
                  {

                     if(isset($this->isHATS[$strCode])){				//检测是否带HATS/for
                        $nHATSFound = $nHATSFound > $this->isHATS[$strCode]?$nHATSFound :$this->isHATS[$strCode];	//只保留高权限的操作
                        if($nHATSFound==2 && $this->isHATS[$strCode]==2) $nSEP++;

                        if($nHATSFound==1)		//当前非HAT内代码，只要保证{}内无连续行判断即可，{}结束后按正常的连续空行进行分段
                        {
                           if($RNCounter>0 && $nBraceFound==-1) $nSEP++;           //无HATS，且不在匹配{}，for前又有多空行，则加一个代码段
                           if( $strCode=="else") $nSEP-=2;
                           //echo $strCode.$strCode.$strCode.$strCode."\n";

                           while($strCode!="{" && $n<count($arrTemp))					//强制检测循环的开始信号，适用于for
                           {
                               $strCode=trim($arrTemp[$n++]);
                               if($strCode!="")  $arrCodeSection[$nSEP][] = $strCode;	//装配数据

                               if($strCode=="{") 
                               {
                                  if($nBraceFound==-1) $nBraceFound=1;
                                  else $nBraceFound++;				//找到{
                               }

                               //echo $n."=2=>SEP:".$nSEP.":RNCOUNTER:". $RNCounter."BRACE:".$nBraceFound."|".$strCode."\n";
                           }
                        }
                        else if($nHATSFound==0)	//需要寻找while();
                        {
                           if($RNCounter>0 && $nBraceFound==-1) $nSEP++;           //无HATS，且不在匹配{}，for前又有多空行，则加一个代码段
                           $bWaitingforWhile=true;
                           $arrCodeSection[$nSEP][] = $strCode;	
                        }
                        else
                           $arrCodeSection[$nSEP][] = $strCode;		//装配数据。由于isset检测过，所以肯定不为空，就不用检测是否为空了。
                     }
                     else
                     {
                        if($nHATSFound>0)			//按{}进行分段，{}内无连续行判断。
                        {
                           if($strCode!='')						//过滤空行，装配有效数据
                              $arrCodeSection[$nSEP][] = $strCode;
                        
                           if( $strCode=='{' ){					//根据大括号来分割
                              if($nBraceFound==-1) 	$nBraceFound+=2;			//初次出现，需要+2以便从-1跳过结束监测点0。
                              else                      $nBraceFound+=1;			//发现一个加一个
                           }
                           else  if( $strCode=='}' )       $nBraceFound--;			//发现一个减一个

                           if($nBraceFound==0 && $nHATSFound>0)			//HATS的强制终止
                           {
                              $nHATSFound=0;
                              $nBraceFound=-1;
                              $nSEP++;						//分段
                           }

                           if($nHATSFound==1)
                           {
                              if($nBraceFound==0) $nHATSFound=0;	//括号结束，回到正常的以连续空行判断的处理中
                           }
                        }
                        else				//按连续行判断是否要分段
                        {
                           if($strCode!="")
                           {
                              if($RNCounter>1 && $bWaitingforWhile==false)
                              {
                                 if($nSEP==-1)   $nSEP=0;
                                 else $nSEP++;
                              }

                              if($strCode=="while") 						//do while
                              {
                                 while($strCode!=";"  && $n<count($arrTemp))
                                 {
                                    $strCode=trim($arrTemp[$n++]);
                                    if($strCode!="")  $arrCodeSection[$nSEP][] = $strCode;	//装配数据
                                 }
                                 $bWaitingforWhile=false;
                              }
                              else
                                 $arrCodeSection[$nSEP][] = $strCode;			//装配数据

                              $RNCounter=0;
                          }
                          else 
                          {
                             $RNCounter++;				//出现空行，就增加计数器
                            //if( $RNCounter>1) $nSEP++;				//连续出现一个以上的空行，数据段偏移量
                          }
                        					//每个循环，只要计数器不增，计数器就归零。
                           //echo $n."=2=>SEP:".$nSEP.":RNCOUNTER:". $RNCounter."|".$strCode."\n";
                        }
                        //echo $n."=2=>SEP:".$nSEP.":FOUND:". $nHATSFound."|STATUS|BRACE:".$nBraceFound."|".$strCode."\n";
                     }
                  }
               }
               $this->arrCODEDATA[2]=$arrCodeSection;				//整段总装
            }
         }
      }

      //调试用
      if($this->arrCODEDATA==Array(NULL,NULL,NULL))
      {
         $this->arrCODEDATA=unserialize(file_get_contents("CODEDATA.txt")); 	//无POST数据，从本地调取数据
      }
      else
      {
         file_put_contents("CODEDATA.txt",serialize($this->arrCODEDATA));	//备份POST过来的数据
      }
      if(DEBUGs) print_r($this->arrCODEDATA);
   }


   //数据处理主入口
   //负责3种类型数据的归并，并调用处理程序对数据进行梳理。
   function deal()
   {
      $arrFuncs = Array(Array(),Array(),Array());		//存放解析后的数据
      foreach($this->arrCODEDATA as $key=>$arr)
      {
         $arrFunc=Array();
         switch($key)
         {
         case 0:						//适用于所有角色的变量
         case 1:						//仅适用于当前角色的变量
            if($arr==NULL) break;
            for($i=0;$i<count($arr);$i++)
            {
                preg_match("/([^^]*?) ([^^]*?)=([^^]*?);/",$arr[$i].";",$arg);
                if(count($arg)==3)
                {
                   $arrFunc[]=Array(uid(),trim($arg[1]),trim($arg[2]));
                }
            }
            $arrFuncs[$key]=$arrFunc;				//收集数据
            break;

         case 2:						//积木块数据
            if($arr==NULL) break;				//数据为空，不处理
            $this->UIDS=Array(NULL,uid());			//parent_uid,this_uid
            $this->nType=($key-2);				//该类型积木放在数组偏移量为0的位置
            foreach($arr as $k=>$blocks)
            {
               $this->bTOPLEVEL="true";
               //var_dump($blocks);
               $this->getFuncs($blocks);				//处理被拆分的代码文本数据，处理完的数据直接放在Blockly[0]里
            }
            break;
         }
      }
      $arrFuncs[2]=$this->Blockly;				//将积木放到指定位置

      return $arrFuncs;
   }


   /*******************  关于积木块的JSON数据    ***************
   //实际类型有不带参数的，带默认值参数的，带变量参数的，以及inputs和fields都有数据的。

   //示例一（参数非变量）：
   {								//积木主体
      "id": "dO9Pm)z^~;pMKM$NRI@B",					//积木块ID
      "opcode": "motion_movesteps",					//积木块的opcode
      "inputs": {							//输入参数
         "STEPS": {
            "name": "STEPS",						//参数名
            "block": "v);_)]M(P#H[OxSM5y-*",				//参数实际对应的积木块ID
            "shadow": "v);_)]M(P#H[OxSM5y-*"				//参数默认值所对应的积木块ID。
         }									//当参数是普通文本或者变量时，block和shadow的值一致；
      },									//不一致时，表示实际值为变量或公式，此时，移除变量或公式积木块，就会显示shadow指向的值。
      "fields": {},							//字段
      "next": null,							//下一块积木的ID
      "topLevel": true,							//积木段的第一块积木，必须是true，否则不会显示
      "parent": null,							//上一块积木的ID，由于此积木是第一块，所以parent数据为null
      "shadow": false,							//不使用shadow指向的参数积木的值
      "x": 54.96296296296293,						//坐标如果无数据，就是（0,0）
      "y": 163.25925925925924
   },
   {								//积木参数，可以是固定值，也可以是变量
      "id": "v);_)]M(P#H[OxSM5y-*",					//参数类型
      "opcode": "math_number",						//math_number:数字  math_whole_number:整数 math_positive_number:正数 TEXT:文本
      "inputs": {},							//非输入类
      "fields": {							//字段类
         "NUM": {
            "name": "NUM",						//数字
            "value": "10"						//实际的值
         }
      },
      "next": null,							//参数不需要指向下一块积木
      "topLevel": false,						//参数不能是true
      "parent": "dO9Pm)z^~;pMKM$NRI@B",					//指向使用本参数的积木块
      "shadow": true							//使用shadow所指向的值。参数默认为true。
   }


   //示例二（参数为变量）：
   {
      "dO9Pm)z^~;pMKM$NRI@B": {
        "id": "dO9Pm)z^~;pMKM$NRI@B",
        "opcode": "motion_movesteps",
        "inputs": {
            "STEPS": {
                "name": "STEPS",
                "block": "@~Y71JbpqFVK5+;;_.pT",			//block指向保存变量的积木块
                "shadow": "v);_)]M(P#H[OxSM5y-*"			//shadow指向保存固定值的积木块
            }
        },
        "fields": {},
        "next": null,
        "topLevel": true,
        "parent": null,
        "shadow": false,
        "x": 54.96296296296293,
        "y": 163.25925925925924
      },
      "v);_)]M(P#H[OxSM5y-*": {						//保存了固定值
        "id": "v);_)]M(P#H[OxSM5y-*",
        "opcode": "math_number",					//数据类型：数字
        "inputs": {},
        "fields": {
            "NUM": {
                "name": "NUM",
                "value": "10"						//移除变量积木块后，主积木上会显示“10”。
            }
        },
        "next": null,
        "topLevel": true,
        "parent": null,
        "shadow": true,
        "x": 105.63888917145903,
        "y": 171.25925925925924
      },
      "@~Y71JbpqFVK5+;;_.pT": {						//保存了变量值
        "id": "@~Y71JbpqFVK5+;;_.pT",
        "opcode": "data_variable",					//数据类型：变量
        "inputs": {},
        "fields": {
            "VARIABLE": {
                "name": "VARIABLE",
                "id": "`jEk@4|i[#Fk?(8x)AV.-my variable",
                "value": "我的变量",					//变量名为“我的变量”
                "variableType": ""
            }
        },
        "next": null,
        "topLevel": false,
        "parent": "dO9Pm)z^~;pMKM$NRI@B",
        "shadow": false,
        "x": 91.96296296296276,
        "y": 167.70370370370358
      }
   }

   //示例三（fields和inputs都有的积木）：
   {
     "jZpHB^?I*NVW1XCy2?8H": {
        "id": "jZpHB^?I*NVW1XCy2?8H",
        "opcode": "data_addtolist",				//向列表list中加入文本“东西”
        "inputs": {						//输入
            "ITEM": {
                "name": "ITEM",
                "block": "%Wc)olM@_$`OyaiEJ8Ki",
                "shadow": "%Wc)olM@_$`OyaiEJ8Ki"
            }
        },
        "fields": {						//字段
            "LIST": {
                "name": "LIST",
                "id": ":}t:07bHbF|aihdeu!o4",
                "value": "我的列表",				//列表名
                "variableType": "list"
            }
        },
        "next": null,
        "topLevel": true,
        "parent": null,
        "shadow": false,
        "x": 57.925925925925895,
        "y": 224.2962962962963
     },
     "%Wc)olM@_$`OyaiEJ8Ki": {
        "id": "%Wc)olM@_$`OyaiEJ8Ki",
        "opcode": "text",
        "inputs": {},
        "fields": {
            "TEXT": {
                "name": "TEXT",
                "value": "东西"					//文本数据
            }
        },
        "next": null,
        "topLevel": false,
        "parent": "jZpHB^?I*NVW1XCy2?8H",
        "shadow": true
     }
   }

   **********************************************************/

   /********************  关于积木块的ID   *******************
      UIDS=Array(NULL,uid());	//默认值（parentuid,thisuid）
      [0]      pop(thisuid)  pop(parentuid) push(thisuid) push(thisuid) push(nextuid)  		//要进入递归程序，需要多压一个thisuid。这个值，在递归结束时，会被下一个积木作为parentuid使用。
         [0]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
         [1]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
         [2]      pop(thisuid)  pop(parentuid) push(thisuid) push(thisuid) push(nextuid)  	//要进入递归程序，需要多压一个thisuid。
            [0]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
            [1]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
            [2]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
                  //清理最后一条数据的next记录
                  pop(nextuid)   pop(thisuid)   push(nextuid)					//递归返回，需要将之前的thisuid删除。递归结束后，由于后续没有积木需要用到最后一个积木的thisuid，所以这个thisuid就作废了。
         [3]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
         [4]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
               //清理最后一条数据的next记录
               pop(nextuid)   pop(thisuid)   push(nextuid)					//递归返回，需要将之前的thisuid删除。
      [1]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
      [2]      pop(thisuid)  pop(parentuid) push(thisuid) push(nextuid)
            //清理最后一条数据的next记录。最后一条积木数据没有next数据。但，不处理似乎也是可以的。
            //输出结果

   **********************************************************/



   //getFuncs主要处理是否包含有子程序的代码段的处理，比如重复执行，循环，条件判断等。
   private function getFuncs($arrCode)
   {
      $acc=count($arrCode);		//文本代码拆分成数组后的长度
      $nHEADE=0;
      for($i=0;$i<$acc;$i++)
      {
         $opcode=$arrCode[$i];
         $nHEADER=$i;
         switch($arrCode[$i])
         {
            //不带参数的HAT积木
            case "event_whenflagclicked":			//当绿旗被点击
            case "event_whenthisspriteclicked":			//当角色被点击
            case "control_start_as_clone":			//当克隆启动时

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

               $this->bTOPLEVEL="false";

               $this->getFuncs($childFunc);			//递归处理子程序集

               $arrBlockTemp=array_pop($this->Blockly);	//已返回，需要清掉最后一条数据的nextuid信息，接下来的积木块，parentuid是进入前的积木的thisuid
               if($arrBlockTemp)
               {
                  $j=json_decode($arrBlockTemp);
                  $j->{'next'}=NULL; 
                  array_push($this->Blockly,json_encode($j));
               }
		//Hats积木块的主信息
               array_push($this->Blockly,'{"id": "'.$thisuid.'", "opcode": "'.$opcode.'",  "inputs": {},  "fields": {},  "next": "'.$nextuid.'",  "topLevel": true,  "parent": null,  "shadow": false}' );

               $uid=array_pop($this->UIDS);			//出栈：next_uid
               array_pop($this->UIDS);				//出栈：parent_uid	//已返回，需要将上一个积木的thisuid（即当前的parent_uid）删除
               array_push($this->UIDS,$uid);			//入栈：next_uid

            break;


            //带参数的HAT积木
            case "event_whenkeypressed":			//当按下某按键
            case "event_whenbackdropswitchesto":		//当背景被切换
            case "event_whenbroadcastreceived":			//当接收到广播消息		//注意：消息需要另外设置一个MSG类型的变量。   //暂未处理。

               $nCount=0;
               $keyPressed=$arrCode[$i+2];			//参数
               $i+=3;
               $childFunc=Array();
               while($i<$acc)
               {
                  //echo $arrCode[$i]."|";
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
                     $childFunc[]=$arrCode[$i];			//需要返回ID
                  }
                  if($nCount==0)				//计数器回到默认状态，说明这个循环可以结束了。
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

               $this->bTOPLEVEL="false";

               $this->getFuncs($childFunc);			//递归处理子程序集

               $arrBlockTemp=array_pop($this->Blockly);	//已返回，需要清掉最后一条数据的nextuid信息，接下来的积木块，parentuid是进入前的积木的thisuid

               if($arrBlockTemp)
               {
                  $j=json_decode($arrBlockTemp);
                  $j->{'next'}=NULL; 
                  array_push($this->Blockly,json_encode($j));
               }
		//Hats积木块的主信息
               array_push($this->Blockly,'{"id": "'.$thisuid.'", "opcode": "'.$opcode.'",  "inputs": {},  "fields":  { "KEY_OPTION": { "name": "KEY_OPTION",  "value": '.$keyPressed.'  } },  "next": "'.$nextuid.'",  "topLevel": true,  "parent": null,  "shadow": false}' );


               $uid=array_pop($this->UIDS);			//出栈：next_uid
               array_pop($this->UIDS);				//出栈：parent_uid	//已返回，需要将上一个积木的thisuid（即当前的parent_uid）删除
               array_push($this->UIDS,$uid);			//入栈：next_uid

            break;



            //具有包含作用的积木  if...then... if...else...  do...while,以及自定义模块
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

$TOPLEVELSTATUS= $this->bTOPLEVEL;//="false";

               $this->bTOPLEVEL="false";

               $this->getFuncs($childFunc);			//递归调用处理子程序集

               $arrBlockTemp=array_pop($this->Blockly);
               if($arrBlockTemp)// && isset($arrBlockTemp["next"]))
               {
                  $j=json_decode($arrBlockTemp);
                  $j->{'next'}=NULL;                  				//清掉了最后一个的nextuid
                  array_push($this->Blockly,json_encode($j));
               }

               $childuid1=uid();
               				//重复执行n次的参数设置
               array_push($this->Blockly,	'{ "id": "'.$childuid1.'", "opcode": "math_whole_number", "inputs": {}, "fields": { "NUM": { "name": "NUM", "value": "'.$strCondition.'" } }, "next": null, "topLevel": false, "parent": "'.$thisuid.'", "shadow": true}' );
               				//重复执行n次的主信息
               array_push($this->Blockly,'{ "id": "'.$thisuid.'", "opcode": "control_repeat", "inputs": { "TIMES": { "name": "TIMES", "block": "'.$childuid1.'", "shadow": "'.$childuid1.'" }, "SUBSTACK": { "name": "SUBSTACK", "block": "'.$nextuid.'", "shadow": null } }, "fields": {}, "next": {}, "topLevel": '.$TOPLEVELSTATUS.', "parent": "'.$parentuid.'", "shadow": false}' );

               $uid=array_pop($this->UIDS);
               array_pop($this->UIDS);
               array_push($this->UIDS,$uid);

            break;

            default:							//其它以“;”结尾的普通函数调用的解析
               $childFunc=Array();
//if($i==0) $this->bTOPLEVEL="true";
//else $this->bTOPLEVEL="false";
               while( $i<$acc && $arrCode[$i]!=";")				//这里是对整个函数的剥离，所以不用考虑参数的多少。
               {
                  $childFunc[]=$arrCode[$i];
                  $i++;
               }

//echo $i."|".$acc."|".$nHEADER."]";
               if(isset($arrCode[$i]))
                  $childFunc[]=$arrCode[$i];					//最后一个“;”一定要加上，否则parseArg里无法识别。

//var_dump($childFunc);
               $this->parseArg($childFunc,(++$i==$acc));			//其它标准函数，都在parseArg里处理

               $this->bTOPLEVEL="false";
            break;
         }
      
      }
   }


/**********************************************************
*
**  拆分解析函数的参数（如果参数是公式，需要调用RPN来处理）
**
**  bLAST   true:最后一条数据，nextuid为空
*
***********************************************************/
   private function parseArg( $arrFunc,$bLAST=false)
   {
      if(!isset($arrFunc[0])) return NULL;

      //print_r($arrFunc);

      $nextuid=$bLAST?'null':uid();


      $nextuid=uid();

      $thisuid=array_pop($this->UIDS);
      
      $parentuid=array_pop($this->UIDS);

      //if($bFIRST==true) $parentuid='null';
//if($bLAST) 
//      array_push($this->UIDS,'null');
//else
      array_push($this->UIDS,$thisuid);

      array_push($this->UIDS,$nextuid);

      switch($arrFunc[0])
      {
         //主调函数的处理方法
         //格式：funName(arg);


      	 /***********************带参数函数，需要处理inputs和fields*************************/
          case "motion_setrotationstyle":					//设置旋转方式			//一个不需要额外参数的特例
            array_push($this->Blockly,'{"id": "'.$thisuid.'", "opcode": "motion_setrotationstyle","inputs": {},"fields": {"STYLE": {"name": "STYLE","value": "'.trim($arrFunc[2],"\"").'"}}, "next": "'.$nextuid.'","topLevel": '.$this->bTOPLEVEL.' ,"parent": '.($parentuid!=''?("\"".$parentuid."\""):"null").',"shadow": false}');

         break;

         //运动
         case "motion_movesteps":			//移动n步
         case "motion_turnright":			//向右转
         case "motion_turnleft":			//向左转
         case "motion_changexby":			//将X坐标增加n
         case "motion_changeyby":			//将Y坐标增加n
         case "motion_setx":				//将X坐标设为
         case "motion_sety":				//将Y坐标设为
         case "motion_pointindirection":		//面向n°方向

         case "motion_glidesecstoxy":		//n秒内滑行到xy
         case "motion_gotoxy":			//将Y坐标设为
         case "motion_goto":			//将Y坐标设为
         case "motion_glideto":
         case "motion_pointtowards":

         //外观
         case "looks_say":
         case "looks_changesizeby":
         case "looks_setsizeto":
         case "looks_think":
         case "looks_sayforsecs":
         case "looks_thinkforsecs":
         case "looks_switchcostumeto":
         case "looks_costume":
         case "looks_switchbackdropto":
         case "looks_backdrops":

         //声音

         //事件
         case "event_broadcast":

         //控制
         case "control_wait":
         ////////////////////////case "control_repeat":		//这个单独在getFuncs里处理。

         //侦测
         case "sensing_distanceto":
         case "sensing_touchingcolor":
         case "sensing_coloristouchingcolor":

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
               array_push($this->Blockly,	'{"id": "'.$childuid1.'", "opcode": "'.$arrChildArg[0][1].'", "inputs": {}, "fields": { "'.$arrChildArg[0][2].'": { "name": "'.$arrChildArg[0][2].'", "value": '.$argArr[0].' } }, "next": null, "topLevel": false, "parent": "'.$thisuid.'", "shadow": true}' );
            }

            if($nCAC>1)							//存在第二个参数
            {
               array_push($this->Blockly,	'{"id": "'.$childuid2.'", "opcode": "'.$arrChildArg[1][1].'", "inputs": {}, "fields": { "'.$arrChildArg[1][2].'": { "name": "'.$arrChildArg[1][2].'", "value": '.$argArr[1].' } }, "next": null, "topLevel": false, "parent": "'.$thisuid.'", "shadow": true}' );
            }

            if($nCAC>2)							//存在第三个参数
            {
               array_push($this->Blockly,	'{"id": "'.$childuid3.'", "opcode": "'.$arrChildArg[2][1].'", "inputs": {}, "fields": { "'.$arrChildArg[2][2].'": { "name": "'.$arrChildArg[2][2].'", "value": '.$argArr[2].' } }, "next": null, "topLevel": false, "parent": "'.$thisuid.'", "shadow": true}' );
            }

            //积木块的主参数
            array_push($this->Blockly,'{"id": "'.$thisuid.'", "opcode": "'.$arrFunc[0].'", "inputs": {'.
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
               .'}, "fields": {}, "next": "'.$nextuid.'", "topLevel": '.$this->bTOPLEVEL.', "parent": '.($parentuid==NULL?"null":"\"".$parentuid."\"").', "shadow": false}');

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
            array_push($this->Blockly,'{ "id": "'.$thisuid.'", "opcode": "'.$arrFunc[0].'", "inputs": {}, "fields": {}, "next": "'.$nextuid.'", "topLevel": '.$this->bTOPLEVEL.', "parent": '.($parentuid==NULL?"{}":"\"".$parentuid."\"").', "shadow": false}' );
         break;

         default:
            return NULL;
      }
      //echo $arrFunc[0]."|". $this->bTOPLEVEL."\n";
      //var_dump($this->Blockly);
      $this->bTOPLEVEL="false";
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
          "motion_movesteps"		=>	Array(Array("STEPS","math_number","NUM")),
         "motion_turnright"		=>	Array(Array("DEGREES","math_number","NUM")),
         "motion_turnleft"		=>	Array(Array("DEGREES","math_number","NUM")),

         "motion_goto"			=>	Array(Array("TO","motion_goto_menu","TO")),
         "motion_gotoxy"		=>	Array(Array("X","math_number","NUM"),Array("Y","math_number","NUM")),
         "motion_glideto" 		=>	Array(Array("SECS","math_number","NUM"),Array("TO","motion_glideto_menu","TO")),
         "motion_glidesecstoxy"		=>	Array(Array("SECS","math_number","NUM"),Array("X","math_number","NUM"),Array("Y","math_number","NUM")),
         "motion_pointindirection"	=>	Array(Array("DIRECTION","math_angle","NUM")),
         "motion_pointtowards" 		=>	Array(Array("TOWARDS","motion_pointtowards_menu","TOWARDS")),

         "motion_changexby"		=>	Array(Array("DX","math_number","NUM")),
         "motion_setx"			=>	Array(Array("X","math_number","NUM")),
         "motion_changeyby"		=>	Array(Array("DY","math_number","NUM")),
         "motion_sety"			=>	Array(Array("Y","math_number","NUM")),

         //"motion_setrotationstyle" 	=>	Array(Array("STYLE",'',"STYLE")),  			//特例。只有一个积木，得改程序了。

         //外观
         "looks_say"			=>	Array(Array("MESSAGE","text","TEXT")),
         "looks_think" 			=>	Array(Array("MESSAGE","text","TEXT")),
         "looks_sayforsecs" 		=>	Array(Array("MESSAGE","text","TEXT"),Array("SEC","math_number","NUM")),
         "looks_thinkforsecs" 		=>	Array(Array("MESSAGE","text","TEXT"),Array("SEC","math_number","NUM")),

         "looks_changesizeby"		=>	Array(Array("CHANGE","math_number","NUM")),
         "looks_setsizeto"		=>	Array(Array("SIZE","math_number","NUM")),


         "looks_switchcostumeto" 	=>	Array(Array("COSTUME","looks_costume","COSTUME")),
         "looks_costume" 		=>	Array(Array("COSTUME","looks_costume","COSTUME")),

         "looks_switchbackdropto" 	=>	Array(Array("BACKDROP","looks_backdrops","BACKDROP")),
         "looks_backdrops" 		=>	Array(Array("BACKDROP","looks_backdrops","BACKDROP")),

         //声音

         //事件
         "event_broadcast" 		=>	Array(Array("BROADCAST_INPUT","event_broadcast_menu","BROADCAST_OPTION")),

         //控制
         "control_wait"			=>	Array(Array("DURATION","math_number","NUM")),//math_positive_number
         //"control_repeat"		=>	Array(Array("SUBSTACK","math_number","NUM"),Array("TIMES","math_number","NUM")),//这个已经转成for了，在getFuncs里处理。

         //侦测
         "sensing_distanceto"		=>	Array(Array("DISTANCETOMENU","sensing_distancetomenu","DISTANCETOMENU")),

         "sensing_touchingcolor" 	=>	Array(Array("COLOR","colour_picker" ,"COLOUR")),
         "sensing_coloristouchingcolor" =>	Array(Array("COLOR","colour_picker" ,"COLOUR"),Array("COLOR2","colour_picker" ,"COLOUR")),


         //运算

         //变量

         //自制积木

         //画笔
         "pen_setPenColorToColor"	=>	Array(Array("COLOR","colour_picker","COLOUR")),
         "pen_changePenColorParamBy"	=>	Array(Array("COLOR_PARAM","pen_menu_colorParam","colorParam"),Array("VALUE","math_number","NUM")),

         //音乐

/*


         "looks_changeeffectby" 	=>	Array(Array("CHANGE","math_number","NUM")),  		//有input，有field，得改程序了。
         "looks_seteffectto" 		=>	Array(Array("VALUE","math_number","NUM")),  		//有input，有field，得改程序了。

         "looks_gotofrontback" 		=>	Array(Array("FRONT_BACK",,"math_number","NUM")),
         "looks_goforwardbackwardlayers"=>	Array(Array("NUM",,)),

         "sound_playuntildone" 		=>	Array(Array("SOUND_MENU","sound_sounds_menu","SOUND_MENU")),
         "sound_play" 			=>	Array(Array("SOUND_MENU","sound_sounds_menu" ,)),

         "sound_changeeffectby" 	=>	Array(Array("VALUE","math_number","NUM")),

         "sound_seteffectto" 		=>	Array(Array("VALUE","math_number","NUM")),

         "sound_changevolumeby" 	=>	Array(Array("VOLUME","sound_volume","NUM")),
         "sound_setvolumeto" 		=>	Array(Array("VOLUME","sound_volume","NUM")),

         "event_whenbroadcastreceived" 	=>	Array(Array("BROADCAST_OPTION","event_broadcast_menu","TEXT")),

         "event_broadcastandwait" 	=>	Array(Array("BROADCAST_INPUT","event_broadcast_menu",,"TEXT")),
         "sensing_of" 			=>	Array(Array("OBJECT","sensing_of_object_menu","BROADCAST_OPTION")),

         "sensing_touchingobject" 	=>	Array(Array("TOUCHINGOBJECTMENU","sensing_touchingobjectmenu","TEXT")),

         "sensing_setdragmode" 		=>	Array(Array("DRAG_MODE",,)),

         "control_create_clone_of" 	=>	Array(Array("CLONE_OPTION","control_create_clone_of_menu" ,)),
*/

      );
      return isset($arrArgName[$opcode])?$arrArgName[$opcode]:Array();
   }
}
?>
