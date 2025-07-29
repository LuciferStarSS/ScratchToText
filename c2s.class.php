<?php
//set_time_limit(3);
/*
1.加载json数据
2.获取所有积木块ID
3.获取所有头部积木块（HATs，此类积木块为每组代码的起始位置）
4.从头部积木块开始解析，并从所有积木块数组中，将已解析的积木块索引删除，剩余的积木块，即为零散积木块，在C中暂时可以舍弃，或者独立放置。
      解析时，需要遍历链表。

*/
include_once "rpn_calc_expression.class.php";			//处理四则混合运算的逆波兰类定义
include_once "rpn_logic_expression.class.php";			//处理逻辑运算的逆波兰类定义

define("DEBUGs", true);
/********************************************
*
**  将类C文本转换成Scratch3.0的JSON数据
*
********************************************/
class CToScratch3
{
   private  $rpn_calc	 = NULL;			//处理四则混合运算的逆波兰类对象
   private  $rpn_logic	 = NULL;			//处理逻辑运算的逆波兰类对象
   private  $arrCODEDATA = Array(NULL,NULL,NULL);	//客户端传过来的代码数据
   private  $Blockly	 = Array();			//代码经解析后生成的积木块数据
   private  $nType	 = 0;				//在对堆栈进行操作时用以区分积木块归属类型：有/无事件触发的积木
   private  $UIDS 	 = NULL;			//用来存储各个积木块间关系的堆栈
   private  $bTOP	 = true;
   private  $bTOPLEVEL   = "true";
   private  $arrVariables= Array();
   private  $arrSelfDefinedFunctions=Array();
   private  $arrLogicBlockParent = Array();
   private  $nCURRENT    = 0;				//当前为代码段内第几个积木
   private  $arrSelfDefinedArgs = Array();		//保存自制积木的本地变量信息
   private  $arrCurrentBlock = "";			//当前自制积木的名字，用于访问指定自制积木的变量信息。

   //根据opcode，获取对应的所有参数的名字
   /*******************************************
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

   Array(Array("inputs的第一个child的name","inputs的第一个child的block对应的数据的opcode","inputs的第一个child的block对应的数据的fields的name"),
         Array("inputs的第二个child的name","inputs的第二个child的block对应的数据的opcode","inputs的第二个child的block对应的数据的fields的name"),
         Array("inputs的第三个child的name","inputs的第三个child的block对应的数据的opcode","inputs的第三个child的block对应的数据的fields的name"));

   *******************************************/
   //积木所对应的参数
   private  $arrArgInfo  = Array(			//每个带有参数的积木，都需要其他积木进行数据支持。不同的数据，表达上存在差异。
      //运动
      "motion_movesteps"			=>	Array(Array("STEPS","math_number","NUM")),					//移动10步
      "motion_turnright"			=>	Array(Array("DEGREES","math_number","NUM")),					//右转
      "motion_turnleft"				=>	Array(Array("DEGREES","math_number","NUM")),					//左转
      "motion_goto"				=>	Array(Array("TO","motion_goto_menu","TO")),					//移到
      "motion_gotoxy"				=>	Array(Array("X","math_number","NUM"),Array("Y","math_number","NUM")),		//移到XY
      "motion_glideto" 				=>	Array(Array("SECS","math_number","NUM"),Array("TO","motion_glideto_menu","TO")),//滑行到
      "motion_glidesecstoxy"			=>	Array(Array("SECS","math_number","NUM"),Array("X","math_number","NUM"),
						              Array("Y","math_number","NUM")),						//n秒内滑行到XY
      "motion_pointindirection"			=>	Array(Array("DIRECTION","math_angle","NUM")),					//面向
      "motion_pointtowards" 			=>	Array(Array("TOWARDS","motion_pointtowards_menu","TOWARDS")),			//面向目标
      "motion_changexby"			=>	Array(Array("DX","math_number","NUM")),						//X坐标增加
      "motion_setx"				=>	Array(Array("X","math_number","NUM")),						//X坐标设为
      "motion_changeyby"			=>	Array(Array("DY","math_number","NUM")),						//Y坐标增加
      "motion_sety"				=>	Array(Array("Y","math_number","NUM")),						//Y坐标设为
      "motion_ifonedgebounce"			=>	Array(),									//遇到边缘就反弹
      "motion_setrotationstyle" 		=>	Array(),									//设置旋转方式

      //外观
      "looks_sayforsecs" 			=>	Array(Array("MESSAGE","text","TEXT"),Array("SEC","math_number","NUM")),		//说几秒
      "looks_say"				=>	Array(Array("MESSAGE","text","TEXT")),						//说
      "looks_thinkforsecs" 			=>	Array(Array("MESSAGE","text","TEXT"),Array("SEC","math_number","NUM")),		//想几秒
      "looks_think" 				=>	Array(Array("MESSAGE","text","TEXT")),						//想
      "looks_switchcostumeto" 			=>	Array(Array("COSTUME","looks_costume","COSTUME")),				//切换造型为
      "looks_nextcostume"			=>	Array(),									//下一个造型
      "looks_switchbackdropto" 			=>	Array(Array("BACKDROP","looks_backdrops","BACKDROP")),				//切换背景为
      "looks_nextbackdrop"			=>	Array(),									//下一个背景
      "looks_changesizeby"			=>	Array(Array("CHANGE","math_number","NUM")),					//将大小增加
      "looks_setsizeto"				=>	Array(Array("SIZE","math_number","NUM")),					//将大小设为
      "looks_changeeffectby"			=>	Array(Array("EFFECT","text","TEXT"),Array("CHANGE","math_number","NUM")),	//将特效增加
      "looks_seteffectto"			=>	Array(Array("EFFECT","text","TEXT"),Array("VALUE","math_number","NUM")),	//将特效设为
      "looks_cleargraphiceffects"		=>	Array(),									//清除图像特效
      "looks_show"				=>	Array(),									//显示
      "looks_hide"				=>	Array(),									//隐藏
      "looks_goforwardbackwardlayers"		=>	Array(),									//上/下移一层
      "looks_gotofrontback"			=>	Array(Array("FRONT_BACK","text","TEXT")),					//置于顶/底层
      //"looks_costume" 			=>	Array(),		//三个变量，待处理。
      //"looks_backdrops" 			=>	Array(),
      //"looks_size" 				=>	Array(),

      //声音
      "sound_playuntildone"			=>	Array(Array("SOUND_MENU","sound_sounds_menu","TEXT")),				//播放声音等待播完
      "sound_play"				=>	Array(Array("SOUND_MENU","sound_sounds_menu","TEXT")),				//播放声音
      "sound_changeeffectby"			=>	Array(Array("EFFECT","text","TEXT"),Array("VOLUME","math_number","NUM")),	//将音效增加
      "sound_seteffectto"			=>	Array(Array("EFFECT","text","TEXT"),Array("VOLUME","math_number","NUM")),	//将音效设为
      "sound_changevolumeby"			=>	Array(Array("VOLUME","math_number","NUM")),					//将音量增加
      "sound_setvolumeto"			=>	Array(Array("VOLUME","math_number","NUM")),					//将音量设为
      "sound_volume"				=>	Array(),									//音量
      "sound_sounds_menu"			=>	Array(),									//播放声音等待播完
      "sound_stopallsounds"			=>	Array(),									//停止所有声音
      "sound_cleareffects"			=>	Array(),									//清除音效

      //事件
      "event_broadcast" 			=>	Array(Array("BROADCAST_OPTION","event_broadcast_menu","BROADCAST_OPTION")),	//广播
      "event_whenflagclicked"			=>	Array(),									//当绿旗被点击
      "event_whenkeypressed"			=>	Array(),									//当按键被点击
      "event_whenthisspriteclicked"		=>	Array(),									//当角色被点击
      "event_whenbackdropswitchesto"		=>	Array(),									//当背景切换到
      "event_whengreaterthan"			=>	Array(),									//当值大于
      "event_whenbroadcastreceived"		=>	Array(),									//当接收到广播消息
      "control_start_as_clone"			=>	Array(),									//当克隆开始
      "event_whenstageclicked"			=>	Array(),									//当舞台被点击

      //互动工具
      "chattingroom_whenChatMessageComes"	=>	Array(),									//当接收到聊天消息
      "chattingroom_sendMsgTo"			=>	Array(Array("USER","text","TEXT"),Array("MSG","text","TEXT")),			//聊天室发送消息
      "chattingroom_lastReceivedMsg"		=>	Array(),									//聊天室接收到的最近一条消息
      "chattingroom_lastReceivedMsgSender"	=>	Array(),									//最后一条未读消息的发送者
      "chattingroom_lastMsgFrom"		=>	Array(Array("USER","text","TEXT")),						//来自某人的最后一条消息
      "chattingroom_sendReport"			=>	Array(Array("STEPS","math_number","NUM"),Array("LEFT","math_number","NUM"),
						              Array("RIGHT","math_number","NUM"),Array("TIME","math_number","NUM")),	//上报信息
      "chattingroom_splitString"		=>	Array(Array("NEEDLE","text","TEXT"),Array("STRTEXT","text","TEXT"),
						              Array("LIST","text","TEXT")),						//聊天室发送消息
      "chattingroom_menu_userlist"		=>	Array(Array("userlist","text","TEXT")),						//聊天室用户列表
      "chattingroom_unreadMsgLength"		=>	Array(),									//未读消息数

      //控制
      "control_wait"				=>	Array(Array("DURATION","math_number","NUM")),					//等待	//math_positive_number
      "control_repeat"				=>	Array(),									//循环  //这个已经转成for了，在getFuncs里处理。
      "control_delete_this_clone"		=>	Array(),									//删除此克隆体
      "for"					=>	Array(),	//这4个，仅在将代码按换行拆分时使用。
      "if"					=>	Array(),
      "do"					=>	Array(),
      "while"					=>	Array(),

      //侦测
      "sensing_username"			=>	Array(),									//当前用户名
      "sensing_mousex"				=>	Array(),									//鼠标X坐标
      "sensing_mousey"				=>	Array(),									//鼠标Y坐标
      "sensing_mousedown"			=>	Array(),									//探测鼠标是否被按下
      "sensing_keypressed"			=>	Array(Array("KEY_OPTION","text","TEXT"),Array("BROADCAST_OPTION","text","TEXT")),//探测某按键是否被按下
      "sensing_dayssince2000"			=>	Array(),									//自2000年开始至今的天数
      "sensing_loudness"			=>	Array(),									//响度
      "sensing_keyoptions"			=>	Array(Array("KEY_OPTION","text","TEXT")),					//按键
      "sensing_setdragmode"			=>	Array(Array("DRAG_MODE","text","TEXT")),					//设置角色是否允许被拖拽
      "sensing_distanceto"			=>	Array(Array("DISTANCETOMENU","sensing_distancetomenu","DISTANCETOMENU")),	//到目标的距离
      "sensing_distancetomenu"			=>	Array(Array("DISTANCETOMENU","text","TEXT")),					//获取到目标的距离的菜单选项
      "sensing_answer"				=>	Array(),									//询问的答案
      "sensing_askandwait"			=>	Array(Array("QUESTION","text","TEXT")),						//询问并等待
      "sensing_timer"				=>	Array(),									//定时器
      "sensing_touchingcolor" 			=>	Array(Array("COLOR","colour_picker" ,"COLOUR")),				//碰到颜色
      "sensing_coloristouchingcolor" 		=>	Array(Array("COLOR","colour_picker" ,"COLOUR"),
						              Array("COLOR2","colour_picker" ,"COLOUR")),				//颜色碰到颜色
      "sensing_touchingobject"			=>	Array(Array("TOUCHINGOBJECTMENU","sensing_of_object_menu","TEXT")),		//碰到对象
      "sensing_touchingobjectmenu"		=>	Array(Array("TOUCHINGOBJECTMENU","sensing_of_object_menu","TEXT")),		//碰到对象的选项菜单
      "sensing_current"				=>	Array(Array("CURRENTMENU","sensing_of_object_menu","TEXT")),			//当前的年月日时分秒
      "sensing_of"				=>	Array(Array("OBJECT","text","TEXT"),Array("PROPERTY","text","TEXT")),		//获取对象的某项参数
      "sensing_of_object_menu"			=>	Array(Array("OBJECT","text","TEXT")),						//对象菜单
      "sensing_resettimer"			=>	Array(),									//计时器归零
      "colour_picker"				=>	Array(Array("COLOUR","text","TEXT")),						//选取颜色

      //运算
      //"operator_add"				=>      Array(Array("NUM1"),Array()),		//运算的，全在代码里实现。

      //变量
      "data_setvariableto"			=>	Array(),									//将变量设为
      "data_showvariable"			=>	Array(Array("VARIABLE","text","VARIABLE")),					//显示变量

      //自制积木

      //画笔
      "pen_clear"				=>	Array(),									//全部擦除
      "pen_stamp"				=>	Array(),									//图章
      "pen_penDown"				=>	Array(),									//落笔
      "pen_penUp"				=>	Array(),									//提笔
      "pen_setPenColorToColor"			=>	Array(Array("COLOR","colour_picker","COLOUR")),					//设置画笔颜色
      "pen_changePenColorParamBy"		=>	Array(Array("COLOR_PARAM","pen_menu_colorParam","colorParam"),
						 	      Array("VALUE","math_number","NUM")),					//增加画笔参数
      "pen_setPenColorParamTo"			=>	Array(Array("COLOR_PARAM","pen_menu_colorParam","colorParam"),
						     	      Array("VALUE","math_number","NUM")),					//设置画笔参数为
      "pen_setPenSizeTo"			=>	Array(Array("SIZE","math_number","NUM")),            				//将笔的粗细设为
      "pen_changePenSizeBy"			=>	Array(Array("SIZE","math_number","NUM")),					//将比的粗细增加
      "pen_menu_colorParam"			=>	Array(),									//画笔参数菜单

       //音乐
      "music_playDrumForBeats"			=>	Array(Array("DRUM","text","TEXT"),Array("BEATS","math_number","NUM")),  	//击打乐器n拍
      "music_restForBeats"			=>	Array(Array("BEATS","math_number","NUM")),  					//休止n拍
      "music_playNoteForBeats"			=>	Array(Array("NOTE","",""),Array("BEATS","math_number","NUM")),  		//演奏音符n拍
      "music_setInstrument"			=>	Array(Array("INSTRUMENT","music_menu_INSTRUMENT","TEXT"),),  			//将乐器设为
      "music_setTempo"				=>	Array(Array("TEMPO","math_number","NUM")),  					//将演奏速度设定为
      "music_changeTempo"			=>	Array(Array("TEMPO","math_number","NUM")),  					//将演奏速度增加
      "music_menu_DRUM"				=>	Array(Array("DRUM","text","TEXT")),  						//乐器列表
      "note"					=>	Array(Array("NOTE","math_number","NUM")),  					//音符
      "music_menu_INSTRUMENT"			=>	Array(Array("INSTRUMENT","text","TEXT")), 
   );

   //HATS
   private $isHATS=Array(						//Hat类型的积木块。头部积木必须在这里注册，否则会不显示。
      "event_whenflagclicked"			=>2,			//  更新：
      "event_whenkeypressed"			=>2,			//    将value改成了key，
      "event_whenthisspriteclicked"		=>2,			//    通过isset来检测，
      "event_whenbackdropswitchesto"		=>2,			//    以规避in_array的速度风险。
      "event_whengreaterthan"			=>2,
      "event_whenbroadcastreceived"		=>2,
      "control_start_as_clone"			=>2,
      "event_whenstageclicked"			=>2,
      "chattingroom_whenChatMessageComes"	=>2,			//当为2时，需要屏蔽{}内的所有连续空行，当}结束时，代码段结束
      //"for"					=>1,			//当为1时，需要屏蔽{}内的所有连续空行，当}结束后，按连续空行来拆分；2覆盖1。
      //"do"					=>0,			//当为1时，需要屏蔽{}内的所有连续空行，当}结束后，按连续空行来拆分；2覆盖1。
      //"while"					=>1,			//当为1时，需要屏蔽{}内的所有连续空行，当}结束后，按连续空行来拆分；2覆盖1。
      //"if"					=>1,			//当为1时，需要屏蔽{}内的所有连续空行，当}结束后，按连续空行来拆分；2覆盖1。
      //"else"					=>1,			//当为1时，需要屏蔽{}内的所有连续空行，当}结束后，按连续空行来拆分；2覆盖1。
      //"void"                                  =>2,			//不用在这里处理了。
									//不存在时，按连续空行来拆分
   );

//遇到HAT，必须以完整的{}配对才能结束
//遇到HAT，nSEP如果已经有代码，则开启新的nSEP++
//无HAT时，遇到{}，在{}配对结束前，不根据连续回车分割代码
//如无上述情况，则以连续回车分割代码

/*
Array
(
    [0] => Array				//适用于所有角色的变量
        (
            [0] => VAR 我的变量 = 0
        )

    [1] => Array				//仅适用于当前角色的变量
        (
            [0] => VAR i = 0
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

            [1] => Array
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

            [2] => Array
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

   //Scratch3.0的20字符ID生成器
   private $soup = '!#%()*+,-./:;=?@[]_`{|}~'.'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'; //不能有“&<>”这三个符号，否则VM生成积木会出现不报错的异常：不显示积木块。
   private function uid()											//运算和逻辑积木块里不能有“^”，否则无法正确识别积木块UID，这里不受影响，但为了保持代码的一致性，也去掉了。
   {
      $id = Array();
      for ($i = 0; $i < 20; $i++) {
         $id[$i] = $this->soup[mt_rand(0,85) ];		//87个字符中随机抽一个
      }
      return implode('',$id);
   }

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
      $this->rpn_calc     = new RPN_CALCULATION_EXPRESSION();		//处理四则混合运算的逆波兰类进行初始化
      $this->rpn_logic    = new RPN_LOGIC_EXPRESSION();			//处理逻辑运算的逆波兰类进行初始化
      $this->arrCODEDATA = unserialize($strCODEDATA);			//处理由serialize处理数组后生成的字符串文本，还原成数组

      for($i=0;$i<3;$i++)			//拆分数据。0和1是舞台和当前角色变量，2是代码。现已不区分有无HATS。
      {
         if($this->arrCODEDATA[$i]!=NULL)
         {
            if($i<2)						//拆分适用于所有角色和仅适用于当前角色的变量。            		
            {
               $old_str=Array('/\/\*([^^]*?)\*\//','/\/\/([^^]*?)\n/','/\n/');			//注释直接删除
               $new_str=Array("","","");
               $this->arrCODEDATA[$i]=preg_replace($old_str,$new_str,$this->arrCODEDATA[$i]);	//变量按分号拆分
               $this->arrCODEDATA[$i]=array_filter(explode(";",$this->arrCODEDATA[$i]));
            }
            else 						//拆分脚本数据
            {
               //由于算法改进，代码段的偏移量不需要连续，所以，只要能准确地分割代码段即可。
               $nSEP		= 0;				//连在一起的代码段数量
               $RNCounter	= 0;				//空行回车计数器
               $arrCodeSection	= Array(); 			//带HATS的代码段，与用多回车分隔的代码段，统一解析在一个数组里。   

                             //大括号  大括号   分号    小括号  小括号    逗号   注释                注释
               $old_str=Array('/\\}/', '/\\{/', '/;/',  '/\\(/', '/\\)/', '/,/','/\/\*([^^]*?)\*\//','/\/\/([^^]*?)\n/');
               $new_str=Array("\n}\n", "\n{\n" ,"\n;\n","\n(\n", "\n)\n", "\n,\n","",                "",       );
               $this->arrCODEDATA[2]=preg_replace($old_str,$new_str,$this->arrCODEDATA[2]);//当前为字符串，先执行无关数据过滤

               $arrTemp=explode("\n",$this->arrCODEDATA[2]); 	//再按回车符拆分成数组
               $bBreakIgnored	= false;			//遇到(){}需要忽略换行计数
               $nBraceCounter	= 0;				//发现{}
               $arrLength	= count($arrTemp);

               $bVoidFound      = false;			//自制积木函数头
               $bBraceEnd       = false;			//大括号结束，用于处理资质积木函数的定义。
               $bElse           = false;			//用于处理if-else，防止else被断开

               for($n=0;$n<$arrLength;$n++)			//遍历处理所有数据
               {
                  $strCode=trim($arrTemp[$n]);				//过滤空格，空行
                  //echo $n."\t\tRNC:".$RNCounter."\t\tBC:".$nBraceCounter."\t\t".$bVoidFound."\t".$bBraceEnd."\t".$nSEP."\t".$strCode."\n";
                  $strCodeArr=explode(" ",$strCode);			//检测关键词中是否存在空格，一般是变量赋值（也可能没有）、四则混合运算和自定义积木中会有。

                  if(count($strCodeArr)>1)				//自定义积木入口和变量赋值处理
                  {
                     if($strCodeArr[0]=="void")					//自制积木，这里处理还没完全实现。。。。。。。。。。。。。。。。。。。。。
                     {
                        $bVoidFound=true;
                        $nSEP++;
                        $arrCodeSection[$nSEP][] = "SELFDEFINED_FUNCTION";			//装配数据
                        $arrCodeSection[$nSEP][] = $strCode;			//装配数据
                     }
                     else if($strCodeArr[1]=="=")				//变量赋值 
                     {
                        $arrCodeSection[$nSEP][] = "data_setvariableto";
                        $arrCodeSection[$nSEP][] = "(";
                        $arrCodeSection[$nSEP][] = $strCodeArr[0];//trim($strCodeArr[0],"VAR_");
                        $arrCodeSection[$nSEP][] = $strCodeArr[2];//trim($strCodeArr[2],"VAR_");
                        $arrCodeSection[$nSEP][] = ")";
                     }
                     else if($strCodeArr[1]=="+=")				//特殊变量赋值 
                     {
                        $arrCodeSection[$nSEP][] = "data_changevariableby";
                        $arrCodeSection[$nSEP][] = "(";
                        $arrCodeSection[$nSEP][] = $strCodeArr[0];//trim($strCodeArr[0],"VAR_");
                        $arrCodeSection[$nSEP][] = $strCodeArr[2];//."+".$strCodeArr[2];//trim($strCodeArr[0],"VAR_")."+".trim($strCodeArr[2],"VAR_");
                        $arrCodeSection[$nSEP][] = ")";
                     }
                     else							//四则混合运算表达式不在这里处理，直接转存。
                     {
                        $arrCodeSection[$nSEP][] = $strCode;
                     }
                  }
                  else							//标准积木数据
                  {
                     if(isset($this->isHATS[$strCode])){			//HATS的处理
                        $arrCodeSection[$nSEP][] = $strCode;			//保存HATS积木块名称
                        $nBraceCounter=0;					//找到 { 时计数

                        while($n<$arrLength-1)					//所包含的积木块前的信息。需要在如下情况下结束循环：1.遇到{；2.当前积木块没有超出积木数据总数。
                        {
                           $strCode=trim($arrTemp[++$n]);				//读取下一个数据
                           if($strCode!="")  $arrCodeSection[$nSEP][] = $strCode;	//装配数据
                           if($strCode=="{") {$nBraceCounter=1;break;}			//找到{，终止循环。为防止遍历完依旧没找到，所以要设置一个控制量$nBraceCounter.
                        }
                        $n++;							//增1，避免数据重复。
                        if($nBraceCounter!=1) return;				//上面的while结束的条件，就是找到了{，如果找不到，就是代码有误，异常了。

                        while( $n<$arrLength-1)					//所包含的积木块的信息。以如下条件同时满足时结束循环：1.遇到最后一个，也即花括号计数器为0；2.当前积木块没有超出积木数据总数。
                        {
                           $strCode=trim($arrTemp[$n++]);
                           if($strCode!="")  $arrCodeSection[$nSEP][] = $strCode;	//装配数据

                           if($strCode=="{")      $nBraceCounter++;			//找到{，$nBraceCounter计数器自增
                           else if($strCode=="}") $nBraceCounter--;			//找到}，$nBraceCounter计数器自减

                           if($nBraceCounter==0) break; 				//找到最后一个}，终止循环
                        }

                        $nSEP++;						//HATS积木处理结束，下一个代码段开始。
                        $nBraceCounter=0;					//{计数器归零
                     }
                     else						//非HATS积木，按照正常的逻辑处理：出现{后，在下一个}前出现的任何多个回车，都需要无视；{}外，连续出现一次以上的回车，表示断开。
                     {
                        if($strCode!="")					//非空，也就意味着不是回车换行
                        {
                           if($strCode=="{")       
                           {
                              //if($arrCodeSection[$nSEP][count($arrCodeSection[$nSEP])-1]==')')  $bBreakIgnored=true;//var_dump($arrCodeSection[$nSEP]);
                              $nBraceCounter++;
                           }								//{括号计数器自增
                           else if($strCode=="}") 
                           {
                              $nBraceCounter--; 
                              if($bElse==true) $bElse=false;		//if else检测
                           }								//{括号计数器自减

                           if(isset($this->arrArgInfo[$strCode]))		//仅在出现积木名称时才判断是否要增段。for,if,do,while等关键词也需要处理。
                           {
                              if($RNCounter>1 && $nBraceCounter==0 && $bElse==false)		//分段标记：$RNCounter>1表示出现了至少三次空行。当前算法，for后面会出现两个空格。
                              {
                                 $nSEP++;						//代码段计数器增1
                              }
                           }

                           if( isset($arrTemp[$n+2])&&$arrTemp[$n+2]=="else"){	//else检测
                              $bElse=true;
                           }

                           if($RNCounter>2 && $bVoidFound==false && $bElse==false) $nSEP++;	//存在多个回车且非自制积木内的无事件代码的分割

                           $arrCodeSection[$nSEP][] = $strCode;			//装配数据

                           if($bBraceEnd && $bVoidFound)
                           {
                              $bVoidFound=false;
                              $bBraceEnd=false;
                              $nSEP++;
                           }//自制积木后面如果有代码，需要截断后续代码。
                           $RNCounter=0;					//有正常数据，空行计数器归零。
                        }
                        else $RNCounter++;				//出现空行，空行计数器增1
                     }
                  }
               }
               $this->arrCODEDATA[2]=$arrCodeSection;		//将整段数据装配
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
      //调试用
      //var_dump($this->arrCODEDATA);
      if(DEBUGs) var_dump($this->arrCODEDATA);
   }


   //数据处理主入口
   //负责3种类型数据的归并，并调用处理程序对数据进行梳理。
   function deal()
   {
      $arrFuncs = Array(Array(),Array(),Array());	//存放解析后的数据
      foreach($this->arrCODEDATA as $key=>$arr)
      {
         $arrFunc=Array();
         switch($key)
         {
         case 0:					//适用于所有角色的变量
         case 1:					//仅适用于当前角色的变量
            if($arr==NULL) break;
            for($i=0;$i<count($arr);$i++)
            {
                preg_match("/([^^]*?) ([^^]*?)=([^^]*?);/",$arr[$i].";",$arg);	//变量按定义格式拆分
                if(count($arg)==4)
                {
                   $arrFunc[]=Array($this->uid(),trim($arg[1]),trim($arg[2]),trim($arg[3]));
                   $this->arrVariables[]=trim($arg[2]);				//变量名另存一份，以便在代码解析中确认是否是变量。
                }
            }
            $arrFuncs[$key]=$arrFunc;			//收集数据
            break;

         case 2:					//积木块数据
            if($arr==NULL) break;			//数据为空，不处理
            //print_r($arr);

            $this->nType=($key-2);			//该类型积木放在数组偏移量为0的位置
            foreach($arr as $key=>$blocks)
            {
               $this->UIDS=Array(NULL,$this->uid());	//每个循环，都是一个独立的代码段，所以每次都需要初始化一下：parent_uid,this_uid

               $this->bTOPLEVEL="true";			//分代码段的意义，在于确认toplevel是否为true。
               $this->nCURRENT=0;
               $this->getFuncs($blocks);		//处理被拆分的代码文本数据，处理完的数据直接放在Blockly[0]里
            }
            break;
         }
      }
      $arrFuncs[2]=$this->Blockly;			//将积木放到指定位置

      if(DEBUGs) print_r($this->Blockly);
      //print_r($this->Blockly);
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

   /********************  关于积木块的UID (Unique ID)  *******************
      UIDS=Array(NULL,$this->uid());	//默认值（parentuid,thisuid）
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
   //有包含的积木，负责拆分后先递归执行包含的积木，再生成主积木。
   //无包含的积木，直接在default中执行生成操作。
   private function getFuncs($arrCode)
   {
      $narrCodeLength=count($arrCode);		//文本代码拆分成数组后的长度
      $nHEADE=0;
      for($i=0;$i<$narrCodeLength;$i++)
      {
         $opcode=$arrCode[$i];
         $nHEADER=$i;
         switch($arrCode[$i])
         {

            case "SELFDEFINED_FUNCTION":			//自制积木的处理

               $nextUID=$this->uid();

               $thisUID=array_pop($this->UIDS);			//出栈：this_uid
               $parentUID=array_pop($this->UIDS);		//出栈：parent_uid
               if($thisUID=='null') {$thisUID=$this->uid();$parentUID=NULL;}
               array_push($this->UIDS,$thisUID);		//入栈：this_uid
               array_push($this->UIDS,$thisUID);		//入栈：this_uid	//将进入下一层，需要多压入一次，以便在返回时仍保留一份数据
               array_push($this->UIDS,$nextUID);		//入栈：next_uid

               $this->bTOPLEVEL="false";

               $prototypeUID=$this->uid();

               //这个地方可有可无，因为后面还有对参数的完整解析
               $strFunctionDefinition=ltrim($arrCode[++$i],"void ");

               $this->arrCurrentBlock=$strFunctionDefinition;				//记录当前属于哪个自制积木，方便积木块中的积木使用本地变量

               preg_match_all("/\[([^^]*?)\]/",$strFunctionDefinition,$m);

               $this->nCURRENT++;

               //解析参数
               $strCondition="";
               $arrCondition=Array();
               //条件
               $i++;							//跳过第一个括号(
               $nBraceCounter=1;					//括号计数器加1
               while($i<$narrCodeLength-1)						//析出(int i=0;i<10;i++)
               {
                  $strCode=$arrCode[++$i];
                  if($strCode=="(") $nBraceCounter++;
                  if($strCode==")") $nBraceCounter--;
                  if($nBraceCounter==0) break;
                  $strCondition.=$strCode;
               }
               $strCondition.=",";


               preg_match_all("/((VAR)|(BOOL)) ([^^]*?),/",$strCondition,$sdf_args);

               $argUIDS=Array();
               $argCounter=count($sdf_args[1]);
               $input_str="";
               $arrInputUIDS=Array();
               $argumentids_str="[";
               $arguments_str="[";
               $proccode_str="";
               $argumentdefaults="[";

               $arrArgName=Array();
               $arrArgType=Array();

               if($argCounter>0)	//有参数
               {
                  //$this->arrSelfDefinedArgs=Array();//新的自制积木，清零。
                                                    //自制积木中的参数变量，只有在自制积木定义块中才有效，所以当第二个自制积木出现时，原有参数即刻失效。
                  //$sdf_args[1] //参数类型
                  //$sdf_args[4] //参数名
                  for($j=0;$j<$argCounter;$j++)		//构建参数积木
                  {
                     //echo "参数定义中";
                     $argUIDS[$j]=$this->uid();
                     array_push($this->Blockly,'{"id": "'.$argUIDS[$j].'", "opcode": "'.(($sdf_args[1][$j]=="VAR")?"argument_reporter_string_number":"argument_reporter_boolean").'", "inputs": {}, "fields": { "VALUE": { "name": "VALUE", "value": "'.$sdf_args[4][$j].'" }}, "next": null, "topLevel": false, "parent": "'.$prototypeUID.'", "shadow": true }');

                     $arrInputUIDS[$j]=$this->uid();
                     if($j>0)
                     {
                        $proccode_str.="";
                        $input_str.=","; 
                        $argumentids_str.=",";
                        $arguments_str.=",";
                        $argumentdefaults.=",";
                     }

                     $arrArgName[$j]='_'.str_replace(" ","",$sdf_args[4][$j]).'_';	//积木proccode的拼接处理准备
                     $arrArgType[$j]=(($sdf_args[1][$j]=="VAR")?' %s ':' %b ');

                     $proccode_str	.=(($sdf_args[1][$j]=="VAR")?'%s':'%b');
                     $input_str	.='"'.$arrInputUIDS[$j].'": {                "name": "'.$arrInputUIDS[$j].'",                "block": "'.$argUIDS[$j].'",                "shadow": "'.$argUIDS[$j].'"            }';
                     $argumentids_str	.='\"'.$arrInputUIDS[$j].'\"';
                     $arguments_str	.='\"'.$sdf_args[4][$j].'\"';
                     $argumentdefaults.=(($sdf_args[1][$j]=="VAR")?'\"\"':'\"false\"');

                     //自制积木的变量。
                     $this->arrSelfDefinedArgs[$strFunctionDefinition][$sdf_args[4][$j]]=$arrInputUIDS[$j];//Array("自制积木名"=>Array("变量名"=>变量UID))
                  }
               }

               //var_dump($this->arrSelfDefinedArgs);
               $argumentids_str.="]";
               $arguments_str.="]";
               $argumentdefaults.="]";
               $proccode_str.="";

               $strFunctionDefinition_format=trim(str_replace($arrArgName,$arrArgType,$strFunctionDefinition));//积木proccode的替换
               $this->arrSelfDefinedFunctions[$strFunctionDefinition]=Array($strFunctionDefinition_format,$sdf_args[1],$sdf_args[4]);

               $i+=2;						//跳过一个积木名和一个(

               //subtrack
               $nBraceCounter=1;
               $childFunc=Array();
               while($i<$narrCodeLength-1)
               {
                  $strCode=$arrCode[$i++];
                  if($strCode=="{")    $nBraceCounter++;
                  if($strCode=="}")    $nBraceCounter--;
                  if($nBraceCounter==0) break;		//计数器回到默认状态，说明这个循环可以结束了。
                  $childFunc[]=$strCode;
               }
               $i--;						//退1

               //array_shift($childFunc);
               //array_pop($childFunc);

               $substackUID="";
               if(count($childFunc)>0)
               {
                  $substackUID=$this->uid();
                  array_push($this->UIDS,$thisUID);		//入栈：this_uid
                  array_push($this->UIDS,$thisUID);		//入栈：this_uid	//将进入下一层，需要多压入一次，以便在返回时仍保留一份数据
                  array_push($this->UIDS,$substackUID);		//入栈：next_uid

                  //$substackUID=$this->uid();			//生成包含的积木的下一个积木的UID
                  //var_dump($this->Blockly);
                  $this->getFuncs($childFunc);			//递归处理子程序集
                  //$this->arrSelfDefinedArgs=NULL;		//数据不需要清掉，因为在调用时需要用到。
               }

               $arrBlockTemp=array_pop($this->Blockly);	//已返回，需要清掉最后一条数据的nextuid信息，接下来的积木块，parentuid是进入前的积木的thisuid
               if($arrBlockTemp)
               {
                  $j=json_decode($arrBlockTemp);
                  if($j!=NULL){
                     $j->{'next'}=NULL; 
                     array_push($this->Blockly,json_encode($j));
                  }
                  else
                     array_push($this->Blockly,$arrBlockTemp);
               }

               $this->arrCurrentBlock="";			//自制积木定义结束，就不需要通过积木名字查局部变量信息了。
		//Hats积木块的主信息
               //$custom_block_UID=$this->uid();
               array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "procedures_definition","inputs": {"custom_block": {"name": "custom_block","block": "'.$prototypeUID.'",                "shadow": "'.$prototypeUID.'"            }        },        "fields": {},        "next": '.($substackUID==""?'null':'"'.$substackUID.'"').',        "topLevel": true,        "parent": null,        "shadow": false    }');
               array_push($this->Blockly,'{"id": "'.$prototypeUID.'","opcode": "procedures_prototype","inputs": {'.$input_str.'},"fields": {},"next": null,"topLevel": false,        "parent": "'.$thisUID.'",        "shadow": true,        "mutation": {            "tagName": "mutation",            "children": [],            "proccode": "'.$strFunctionDefinition_format.'",            "argumentids": "'.$argumentids_str.'",            "argumentnames": "'.$arguments_str.'",            "argumentdefaults": "'.$argumentdefaults.'",            "warp": "false"        }    }');

               //$nextUID=
               //array_pop($this->UIDS);			//出栈：next_uid
               //array_pop($this->UIDS);			//出栈：parent_uid

               array_pop($this->UIDS);				//出栈：next_uid
               array_pop($this->UIDS);				//出栈：parent_uid	//已返回，需要将上一个积木的thisuid（即当前的parent_uid）删除
               array_push($this->UIDS,$nextUID);		//入栈：next_uid

               break;




            //这三个不带参数的HAT积木，有相同的结构
            case "event_whenflagclicked":			//当绿旗被点击
            case "event_whenthisspriteclicked":			//当角色被点击
            case "control_start_as_clone":			//当克隆启动时

               $this->nCURRENT++;
               $nBraceCounter=1;
               $i+=4;						//跳过一个积木名和一个(
               $childFunc=Array();
               while($i<$narrCodeLength-1)
               {
                  $strCode=$arrCode[$i++];
                  if($strCode=="{")    $nBraceCounter++;
                  if($strCode=="}")    $nBraceCounter--;
                  if($nBraceCounter==0) break;		//计数器回到默认状态，说明这个循环可以结束了。
                  $childFunc[]=$strCode;
               }
               $i--;						//退1

               //array_shift($childFunc);
               //array_pop($childFunc);

               $nextUID=$this->uid();

               $thisUID=array_pop($this->UIDS);			//出栈：this_uid
               if($thisUID=='null')  {$thisUID=$this->uid();$parentUID=NULL;}
               $parentUID=array_pop($this->UIDS);		//出栈：parent_uid
               array_push($this->UIDS,$thisUID);		//入栈：this_uid
               array_push($this->UIDS,$thisUID);		//入栈：this_uid	//将进入下一层，需要多压入一次，以便在返回时仍保留一份数据
               array_push($this->UIDS,$nextUID);		//入栈：next_uid

               $this->bTOPLEVEL="false";

               $this->getFuncs($childFunc);			//递归处理子程序集

               $arrBlockTemp=array_pop($this->Blockly);	//已返回，需要清掉最后一条数据的nextuid信息，接下来的积木块，parentuid是进入前的积木的thisuid
               if($arrBlockTemp)
               {
                  $j=json_decode($arrBlockTemp);
                  if($j!=NULL){
                     $j->{'next'}=NULL; 
                     array_push($this->Blockly,json_encode($j));
                  }
                  else
                     array_push($this->Blockly,$arrBlockTemp);
               }

		//Hats积木块的主信息
               array_push($this->Blockly,'{"id": "'.$thisUID.'", "opcode": "'.$opcode.'",  "inputs": {},  "fields": {},  "next": "'.$nextUID.'",  "topLevel": true,  "parent": null,  "shadow": false}' );

               //$nextUID=
               array_pop($this->UIDS);				//出栈：next_uid
               array_pop($this->UIDS);				//出栈：parent_uid	//已返回，需要将上一个积木的thisuid（即当前的parent_uid）删除
               array_push($this->UIDS,$nextUID);		//入栈：next_uid

            break;


            //这个带参数的HAT积木，比较特别
            case "event_whenkeypressed":			//当按下某按键

               $this->nCURRENT++;
               $nBraceCounter=0;
               $keyPressed=trim($arrCode[$i+2],"\"");			//参数
               $i+=3;
               $childFunc=Array();
               while($i<$narrCodeLength)
               {
                  $i++;
                  if($arrCode[$i]=="{") 
                  {
                     $childFunc[]=$arrCode[$i];	$nBraceCounter++;
                  }
                  else if($arrCode[$i]=="}") 
                  {
                     $childFunc[]=$arrCode[$i];	$nBraceCounter--;
                  }
                  else
                  {
                     $childFunc[]=$arrCode[$i];			//需要返回ID
                  }
                  if($nBraceCounter==0)				//计数器回到默认状态，说明这个循环可以结束了。
                  {
                     break;
                  }
               }

               array_shift($childFunc);
               array_pop($childFunc);

               $nextuid=$this->uid();

               $thisUID=array_pop($this->UIDS);		//出栈：this_uid
               if($thisUID=='null')  {$thisUID=$this->uid();$parentUID=NULL;}
               $parentuid=array_pop($this->UIDS);		//出栈：parent_uid
               array_push($this->UIDS,$thisUID);		//入栈：this_uid
               array_push($this->UIDS,$thisUID);		//入栈：this_uid	//将进入下一层，需要多压入一次，以便在返回时仍保留一份数据
               array_push($this->UIDS,$nextuid);		//入栈：next_uid

               $this->bTOPLEVEL="false";

               $this->getFuncs($childFunc);			//递归处理子程序集

               $arrBlockTemp=array_pop($this->Blockly);	//已返回，需要清掉最后一条数据的nextuid信息，接下来的积木块，parentuid是进入前的积木的thisUID

               if($arrBlockTemp)
               {
                  $j=json_decode($arrBlockTemp);
                  $j->{'next'}=NULL; 
                  array_push($this->Blockly,json_encode($j));
               }
		//Hats积木块的主信息
               array_push($this->Blockly,'{"id": "'.$thisUID.'", "opcode": "'.$opcode.'",  "inputs": {},  "fields":  { "KEY_OPTION": { "name": "KEY_OPTION",  "value": "'.$keyPressed.'"  } },  "next": "'.$nextuid.'",  "topLevel": true,  "parent": null,  "shadow": false}' );


               $uid=array_pop($this->UIDS);			//出栈：next_uid
               array_pop($this->UIDS);				//出栈：parent_uid	//已返回，需要将上一个积木的thisUID（即当前的parent_uid）删除
               array_push($this->UIDS,$uid);			//入栈：next_uid

            break;

            //这个带参数的HAT积木，比较特别
            case "event_whenbackdropswitchesto":		//当背景被切换

               $this->nCURRENT++;
               $nBraceCounter=0;
               $strBACKDROP=trim($arrCode[$i+2],"\"");			//参数
               $i+=3;
               $childFunc=Array();
               while($i<$narrCodeLength)
               {
                  //echo $arrCode[$i]."|";
                  $i++;
                  if($arrCode[$i]=="{") 
                  {
                     $childFunc[]=$arrCode[$i];	$nBraceCounter++;
                  }
                  else if($arrCode[$i]=="}") 
                  {
                     $childFunc[]=$arrCode[$i];	$nBraceCounter--;
                  }
                  else
                  {
                     $childFunc[]=$arrCode[$i];			//需要返回ID
                  }
                  if($nBraceCounter==0)				//计数器回到默认状态，说明这个循环可以结束了。
                  {
                     break;
                  }
               }

               array_shift($childFunc);
               array_pop($childFunc);

               $nextuid=$this->uid();

               $thisUID=array_pop($this->UIDS);		//出栈：this_uid
               if($thisUID=='null')  {$thisUID=$this->uid();$parentUID=NULL;}
               $parentuid=array_pop($this->UIDS);		//出栈：parent_uid
               array_push($this->UIDS,$thisUID);		//入栈：this_uid
               array_push($this->UIDS,$thisUID);		//入栈：this_uid	//将进入下一层，需要多压入一次，以便在返回时仍保留一份数据
               array_push($this->UIDS,$nextuid);		//入栈：next_uid

               $this->bTOPLEVEL="false";

               $this->getFuncs($childFunc);			//递归处理子程序集

               $arrBlockTemp=array_pop($this->Blockly);	//已返回，需要清掉最后一条数据的nextuid信息，接下来的积木块，parentuid是进入前的积木的thisUID

               if($arrBlockTemp)
               {
                  $j=json_decode($arrBlockTemp);
                  $j->{'next'}=NULL; 
                  array_push($this->Blockly,json_encode($j));
               }

               //Hats积木块的主信息
               array_push($this->Blockly,'{"id": "'.$thisUID.'", "opcode": "'.$opcode.'",  "inputs": {},  "fields":  {"BACKDROP": {"name": "BACKDROP","value": "'.$strBACKDROP.'"}},  "next": "'.$nextuid.'",  "topLevel": true,  "parent": null,  "shadow": false}' );

               $uid=array_pop($this->UIDS);			//出栈：next_uid
               array_pop($this->UIDS);				//出栈：parent_uid	//已返回，需要将上一个积木的thisUID（即当前的parent_uid）删除
               array_push($this->UIDS,$uid);			//入栈：next_uid

            break;

            //这个带参数的HAT积木，比较特别
            case "event_whenbroadcastreceived":			//当接收到广播消息		//注意：消息需要另外设置一个MSG类型的变量。   //暂未处理。

               $this->nCURRENT++;
               $nBraceCounter=0;
               $strMESSAGE=trim($arrCode[$i+2],"\"");			//参数
               $i+=3;
               $childFunc=Array();
               while($i<$narrCodeLength)
               {
                  //echo $arrCode[$i]."|";
                  $i++;
                  if($arrCode[$i]=="{") 
                  {
                     $childFunc[]=$arrCode[$i];	$nBraceCounter++;
                  }
                  else if($arrCode[$i]=="}") 
                  {
                     $childFunc[]=$arrCode[$i];	$nBraceCounter--;
                  }
                  else
                  {
                     $childFunc[]=$arrCode[$i];			//需要返回ID
                  }
                  if($nBraceCounter==0)				//计数器回到默认状态，说明这个循环可以结束了。
                  {
                     break;
                  }
               }

               array_shift($childFunc);
               array_pop($childFunc);

               $nextuid=$this->uid();

               $thisUID=array_pop($this->UIDS);			//出栈：this_uid
               if($thisUID=='null')  {$thisUID=$this->uid();$parentUID=NULL;}
               $parentuid=array_pop($this->UIDS);		//出栈：parent_uid
               array_push($this->UIDS,$thisUID);		//入栈：this_uid
               array_push($this->UIDS,$thisUID);		//入栈：this_uid	//将进入下一层，需要多压入一次，以便在返回时仍保留一份数据
               array_push($this->UIDS,$nextuid);		//入栈：next_uid

               $this->bTOPLEVEL="false";

               $this->getFuncs($childFunc);			//递归处理子程序集

               $arrBlockTemp=array_pop($this->Blockly);	//已返回，需要清掉最后一条数据的nextuid信息，接下来的积木块，parentuid是进入前的积木的thisUID

               if($arrBlockTemp)
               {
                  $j=json_decode($arrBlockTemp);
                  $j->{'next'}=NULL; 
                  array_push($this->Blockly,json_encode($j));
               }
		//Hats积木块的主信息
               array_push($this->Blockly,'{"id": "'.$thisUID.'", "opcode": "'.$opcode.'",  "inputs": {},  "fields":  {"BROADCAST_OPTION": {"name": "BROADCAST_OPTION","id": "ARGUID","value": "'.$strMESSAGE.'","variableType": "broadcast_msg"}},  "next": "'.$nextuid.'",  "topLevel": true,  "parent": null,  "shadow": false}' );

               $uid=array_pop($this->UIDS);			//出栈：next_uid
               array_pop($this->UIDS);				//出栈：parent_uid	//已返回，需要将上一个积木的thisUID（即当前的parent_uid）删除
               array_push($this->UIDS,$uid);			//入栈：next_uid

            break;


             //这个非HAT积木，比较特别
            //具有包含作用的积木  if...then... if...else...  do...while,以及自定义模块
            case "do":
               //var_dump($arrCode);
               //$bIFELSE=in_array("else",$arrCode);						//快速确认有没有else
              
               $thisUID=array_pop($this->UIDS);			//出栈：this_uid
               if($thisUID=='null')  {$thisUID=$this->uid();$parentUID=NULL;}
               array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "control_repeat_until","inputs": {},"fields": {},"next": null,"topLevel": true,"parent": null,"shadow": false}');

            break;


             //这个非HAT积木，比较特别
            case "if":               //if(条件){第一分支}else{第二分支}

               $thisUID=array_pop($this->UIDS);			//取出当前，也即上一个主block生成的nextuid
               if($thisUID=='null')  {$thisUID=$this->uid();$parentUID=NULL;}
               $parentUID=array_pop($this->UIDS);
               $nextUID=$this->uid();
               $TOPLEVELSTATUS= $this->bTOPLEVEL;
               $this->bTOPLEVEL="false";			//后续代码的toplevel必为false，true只在deal第一次进入时设置。

               $arrChildUID=Array();
               $strCondition="";
               $arrChildBlocks1=Array();
               $arrChildBlocks2=Array();
               $arrCondition=Array();
               //条件
               $i++;							//跳过第一个括号(
               $nBraceCounter=1;					//括号计数器加1
               while($i<$narrCodeLength-1)						//析出(int i=0;i<10;i++)
               {
                  $strCode=$arrCode[++$i];
                  if($strCode=="(") $nBraceCounter++;
                  if($strCode==")") $nBraceCounter--;
                  if($nBraceCounter==0) break;
                  $strCondition.=$strCode;
               }

               $condition_input='';
               $condition_input_id='';
               $condition_returnArr=Array();
               if($strCondition)						//解析存在的判断条件
               {

                  $arrMainProcedure=$this->rpn_logic->init($strCondition);
                  //echo "arrMainProcedure\r\n";
                  //print_r($arrMainProcedure);
                  $mpCounter=count($arrMainProcedure);
                  for($mp=0;$mp<$mpCounter;$mp++)
                  {
                     if($mp==0)
                        $arrChildUID=$this->parseLogicExpression($arrMainProcedure[$mp],$thisUID);	//头部积木UID要用。
                     else $this->parseLogicExpression($arrMainProcedure[$mp],$arrChildUID[0]);
                     //print_r($arrChildUID);
                  }
                  //$arrCondition=$arrResult;

                  if(isset($arrChildUID[0]) && $arrChildUID[0])
                  {
                     $condition_input=' "CONDITION":{  "name": "CONDITION",   "block": "'.$arrChildUID[0].'",    "shadow": null}'; //condition的shadow为null
                  }
               }
               else	//当条件里表达错误时（比如在自定义积木之外使用其参数变量，变量名不存在以及表达式错误：Scratch不支持if(1){}这种表达），显示无条件状态。
                  $condition_input=' "CONDITION":{  "name": "CONDITION",   "block": null,    "shadow": null}'; //condition的shadow为null

               //第一分支：if
               if($arrCode[++$i]=="{")				//由于空格和回车已经被屏蔽，所以if(条件)后必定是{或者下一个命令
               {
                  $nBraceCount=1;
                  $i++;
                  while($i<$narrCodeLength)					//析出SUBSTACK
                  {
                     $strCode=$arrCode[$i++];
                     if($strCode=="{")    $nBraceCount++;
                     if($strCode=="}")    $nBraceCount--;

                     if($nBraceCount==0) break;			//计数器回到默认状态，说明这个循环可以结束了。
                     $arrChildBlocks1[]=$strCode;
                  }
               }
               else
               {
                  while($i<$narrCodeLength)					//if后面没有{}，则以;结束
                  {
                     $strCode=$arrCode[$i++];
                     $arrChildBlocks1[]=$strCode;
                     if($strCode==";")    break;
                  }
               }
               $substack1='';					//构建SUBSTACK的JSON数据
               if(count($arrChildBlocks1)>0)
               {
                  $substackUID=$this->uid();
                  array_push($this->UIDS,$thisUID);		//入栈：this_uid
                  array_push($this->UIDS,$thisUID);		//入栈：this_uid	//将进入下一层，需要多压入一次，以便在返回时仍保留一份数据
                  array_push($this->UIDS,$substackUID);		//入栈：next_uid

                  $substack1=', "SUBSTACK":{"name": "SUBSTACK","block": "'.$substackUID.'","shadow": null}';//开始位置要加“,”。

                  //$nextUID=$this->uid();//nextuid和substackUID要分清。。。nextuid已经被使用，那么主积木的nextuid就应该重新生成一个。

                  $this->getFuncs($arrChildBlocks1);			//递归处理子程序集
               }

               //第二分支:else
               $n=array_search("else",$arrCode);			//else代码段
               $bIFELSE=false;
               if($n) $bIFELSE=true;					//存在else，这个search有点问题，需要优化一下。记得之前处理过了，代码被回滚了？
               //var_dump($arrCode);
               if(isset($arrCode[$i]) && $arrCode[$i]=="else")			//有else
               {
                  echo "有ELSEEEEEEEEEEEEEEEEEEEE\n";

                  if($arrCode[$i+1]=="{")		//有括号{
                  {
                     $nBraceCount=1;
                     $i+=2;
                     while($i<$narrCodeLength)					//析出SUBSTACK
                     {
                        $strCode=$arrCode[$i++];
                        if($strCode=="{")    $nBraceCount++;
                        if($strCode=="}")    $nBraceCount--;

                        if($nBraceCount==0) break;			//计数器回到默认状态，说明这个循环可以结束了。
                        $arrChildBlocks2[]=$strCode;
                     }
                  }
                  else					//无括号{
                  {
                     $i++;
                     while($i<$narrCodeLength)					//else后面没有{}，则以;结束
                     {
                        $strCode=$arrCode[$i++];
                        $arrChildBlocks2[]=$strCode;
                        if($strCode==";")    break;
                     }
                  }
               }
               if(!isset($arrCode[$i]))   $nextUID='';	//当前是本代码段最后一个积木？

               $i--;					//退1

               $substack2='';					//构建SUBSTACK2的JSON数据
               if(count($arrChildBlocks2)>0)
               {
                  $substackUID=$this->uid();
                  array_push($this->UIDS,$thisUID);		//入栈：this_uid
                  array_push($this->UIDS,$thisUID);		//入栈：this_uid	//将进入下一层，需要多压入一次，以便在返回时仍保留一份数据
                  array_push($this->UIDS,$substackUID);		//入栈：next_uid

                  $substack2=',"SUBSTACK2":{"name": "SUBSTACK2","block": "'.$substackUID.'","shadow": null}';//开始位置要加“,”。

                  //$nextUID=$this->uid();//nextuid已经被使用，那么主积木的nextuid就应该重新生成一个。

                  $this->getFuncs($arrChildBlocks2);			//递归处理子程序集

               }

               //构建if-else的完整数据
               array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "control_if'.($bIFELSE?'_else':'').'","inputs": {'.$condition_input.' '.$substack1.' '.$substack2.'},"fields": {},"next": '.($nextUID?'"'.$nextUID.'"':'null').',"topLevel": '.$TOPLEVELSTATUS.',"parent": '.($parentUID?'"'.$parentUID.'"':'null').',"shadow": false}');

               $uid=array_pop($this->UIDS);			//出栈：next_uid
               array_pop($this->UIDS);				//出栈：parent_uid	//已返回，需要将上一个积木的thisUID（即当前的parent_uid）删除
               //array_push($this->UIDS,$uid);			//入栈：next_uid
               array_push($this->UIDS,$nextUID);			//入栈：next_uid

            break;


            //这个非HAT积木，比较特别
            case "for":					//for循环比hats多了一个循环条件参数的解析。

               $thisUID=array_pop($this->UIDS);			//上一个积木生成的nextuid
               $parentUID=array_pop($this->UIDS);		//上一个积木的thisUID
               array_push($this->UIDS,$thisUID);		//因为for要进入递归，所以压入两次
               array_push($this->UIDS,$thisUID);

               $thisUID=array_pop($this->UIDS);			//上一个积木生成的nextuid
               if($thisUID=='null')  {$thisUID=$this->uid();$parentUID=NULL;}
               $parentUID=array_pop($this->UIDS);		//上一个积木的thisUID
               array_push($this->UIDS,$thisUID);		//因为要进入递归，所以压入两次
               array_push($this->UIDS,$thisUID);

               $nLOOP=-1;					//循环次数
               $strLoopCondition='';
               $nBraceCounter=0;

               $i++;						//到下一个数据：(
               while($i<$narrCodeLength-1)			//获取循环次数表达：(int i=0;i<10;i++)
               {
                  $strCode=$arrCode[$i++];
                  if($strCode=="{") {$nBraceCounter=1;break;}	//遇到{就结束
                  $strLoopCondition.=$strCode;
               }

               $arrLoopCondition=Array();			//解析循环条件
               preg_match_all("/int ([^^]*?)=([^^]*?);([^^]*?)<([^^]*?);/",$strLoopCondition,$m);
               if(count($m)==5)
               {
                  if(!is_numeric($m[4][0]))// && $m[4][0]!="")		//如果i<后面非数字，则应该对其进行算术表达式的解析
                  {
                     $strLoop=$m[4][0]."-".$m[2][0];			//C/C++中，i的初始值可以为任何数，但在Scratch中，这个i的初始值为多少并没有意义，只需要知道循环执行多少次，
                     if($m[2][0]=='0')   $strLoop=$m[4][0];		//所以只需要计算i最大值与i初始值之间的差值即可。

                     if($strLoop=="") $nLOOP=10;			//条件缺失，默认重复次数为10
                     else
                     {
                        $arrLoopCondition=$this->rpn_calc->init($strLoop);

                        if($arrLoopCondition===TRUE)					//拆分成功
                        {
                           $arrLoopCondition=$this->rpn_calc->toScratchJSON();
                           //echo "条件结果：";
                           //var_dump($arrLoopCondition);
                           $arrChildUIDX=$this->parseCalculationExpression(Array("NUM","math_number","NUM"),$arrLoopCondition,$thisUID);
                        }
                        else								//拆分失败
                        {
                           if(in_array(trim($arrLoopCondition),$this->arrVariables) )	//参数是已定义的变量，生成该变量的积木块，此处不需要shadow，shadow由repeat自己生成。
                           {
                              $arrChildUIDX[0]=$this->uid();
                              array_push($this->Blockly, '{"id": "'.$arrChildUIDX[0].'","opcode": "data_variable","inputs": {},"fields": {"VARIABLE": {"name": "VARIABLE","id": "'.$this->uid().'","value": "'.trim($arrLoopCondition).'","variableType": ""}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": false}');
                           }
                           else $nLOOP=10;						//非有效变量，重复次数默认为10。
                        }
                     }
                  }
                  else							//是可以识别的数字，则直接以数字处理
                  {							//这里忽略了i的初始值可能为变量的情况。
                     $nLOOP=intval($m[4][0]-$m[2][0]);			//这种情况，在Scratch中无意义。
                  }
               }

               if($nBraceCounter!=1) break;				//前面寻找循环次数时，就是以发现{结束的，所以此处计数器应为1，不为1应该是出错了。
               $childFunc=Array();					//析出SUBSTACK中的数据
               while($i<$narrCodeLength-1)
               {
                  $strCode=$arrCode[$i++];
                  if($strCode=="{")    $nBraceCounter++;
                  if($strCode=="}")    $nBraceCounter--;
                  if($nBraceCounter==0) break;		//计数器回到默认状态，说明这个循环可以结束了。
                  $childFunc[]=$strCode;
               }
               $i--;						//退1

               $TOPLEVELSTATUS= $this->bTOPLEVEL;

               $this->bTOPLEVEL="false";
               $substack='';
               if(count($childFunc)>0)
               {
                  $substackUID=$this->uid();			//生成包含的积木的下一个积木的UID
                  array_push($this->UIDS,$substackUID);		//压入一次nextuid
                  $this->getFuncs($childFunc);			//递归调用处理子程序集

                  $arrBlockTemp=array_pop($this->Blockly);		//子集末尾的next为null
                  if($arrBlockTemp)
                  {
                     $j=json_decode($arrBlockTemp);
                     $j->{'next'}=NULL;                  				//清掉了最后一个的nextuid
                     array_push($this->Blockly,json_encode($j));
                  }

                  $substack=',"SUBSTACK": { "name": "SUBSTACK", "block": "'.$substackUID.'", "shadow": null } ';
               }

               $childUID=$this->uid();
               $nextUID=$this->uid();

               if($parentUID==$thisUID)   $parentUID='';

               //重复执行n次的参数设置
               if($nLOOP==-1)					//循环次数为0，表示循环条件为算术表达式
               {
                  //shadow
                  array_push($this->Blockly,  '{"id": "'.$childUID.'", "opcode": "math_whole_number", "inputs": {}, "fields": { "NUM": { "name": "NUM", "value": "'.$nLOOP.'" } }, "next": null, "topLevel": false, "parent": "'.$thisUID.'", "shadow": true}' );
               	  //重复执行n次的主信息
                  array_push($this->Blockly,  '{"id": "'.$thisUID.'", "opcode": "control_repeat", "inputs": { "TIMES": { "name": "TIMES", "block": "'.$arrChildUIDX[0].'", "shadow": "'.$childUID.'" } '.$substack.'}, "fields": {}, "next": '.(($nextUID)?'"'.$nextUID.'"':'null').', "topLevel": '.$TOPLEVELSTATUS.', "parent": '.(($parentUID)?'"'.$parentUID.'"':'null').', "shadow": false}' );
               }
               else						//直接用$nLOOP作为循环次数
               {
                  //次数
                  array_push($this->Blockly,  '{"id": "'.$childUID.'", "opcode": "math_whole_number", "inputs": {}, "fields": { "NUM": { "name": "NUM", "value": "'.$nLOOP.'" } }, "next": null, "topLevel": false, "parent": "'.$thisUID.'", "shadow": true}' );
               	  //重复执行n次的主信息
                  array_push($this->Blockly,  '{"id": "'.$thisUID.'", "opcode": "control_repeat", "inputs": { "TIMES": { "name": "TIMES", "block": "'.$childUID.'", "shadow": "'.$childUID.'" } '.$substack.' }, "fields": {}, "next": '.(($nextUID)?'"'.$nextUID.'"':'null').', "topLevel": '.$TOPLEVELSTATUS.', "parent": '.(($parentUID)?'"'.$parentUID.'"':'null').', "shadow": false}' );
               }

               array_pop($this->UIDS);			//弹出回调中返回的nextuid，舍弃。
               array_pop($this->UIDS);			//丢弃一次thisUID，也就是parentuid
               array_push($this->UIDS,$nextUID);		//生成新的uid

            break;


            //其它普通无包含关系的积木，在这里处理。
            default:					//其它以“;”结尾的普通函数调用的解析

               $childFunc=Array();
               while( $i<$narrCodeLength)			//这里是对整个函数的剥离，所以不用考虑参数的多少，直接以;结束。
               {
                  $strCode=$arrCode[$i++];
                  $childFunc[]=$strCode;			//先采集，再终止
                  if($strCode==";") break;
               }

               $bLastBlock=!isset($arrCode[$i])?true:false;		//当前是代码段中最后一个积木？

               $i--;						//退1

               $this->parseArg($childFunc,$bLastBlock);			//其它标准函数，都在parseArg里处理

               $this->bTOPLEVEL="false";
            break;
         }
      }
   }

   /******************************************************************************
   *
   *    利用逆波兰序算法，将复杂的逻辑运算表达式，拆成若干个由两个数组成的简单表达式，每个表达式，在这里被转换成对应的积木。
   *
   *     且非或优先级：
   *     ! > && > ||
   *
   *     大于小于等于，需要等且非或生成后再处理。
   *
   *     这类积木块，需要返回最顶部的那块积木的ID，按照当前算法，是最后生成的积木块的ID。
   *     每块参数积木的ID，都由自己生成，而不是从参数那里传递过来。
   *
   *     $dataArg：			积木块信息
   *     $parentUID:			上一块积木的UID
   *     返回：arrChildBlockUID:	Array(参数1UID,参数2UID);
   *
   *     逻辑判断不需要shadow
   *
   **********************************************************************************/
/********************************************
传入的$dataArg为数组，其中任一数据格式为：Array(逻辑运算符,UID,第一个参数,第二个参数)
参数可能是：
1.纯数字
2.变量
3.计算表达式

其中3.计算表达式需要预处理：
1.拆分
2.生成
3.返回最终积木的uid
4.将返回的UID替换相应的参数


gt直接接add，而不是text

********************************************/

   private function parseLogicExpression($dataArg,$parentUID)
   {
      if(is_array($dataArg) && count($dataArg[0])==2)//参数为仅有的自制积木的判断条件或单个判断函数
      {
         //当表达式形如“if(a){}”，其中a为布尔值类型参数，
         //则经过处理后，只会有两个数据被传入：UID和变量名，
         //处理操作在此实现

         if(isset($this->arrSelfDefinedArgs[$this->arrCurrentBlock][$dataArg[0][1]]))	//确认是自制积木内的已定义参数
         {
            //echo "自制积木的变量：";
            $blockUID=$this->uid();
            //block对变量名直接引用，不需要shadow
            array_push($this->Blockly, '{"id": "'.$blockUID.'","opcode":"argument_reporter_boolean","inputs": {},"fields": {"VALUE": {"name": "VALUE","value": "'.$dataArg[0][1].'"}},"next": null,"topLevel": false,"parent":  "'.$parentUID.'","shadow":false}');
            return Array($blockUID);
         }

         //当公式里有太多无意义的括号时，在这里处理。例如：if(1>((我的变量+1))){}
         else if($this->rpn_calc->init($dataArg[0][1])==TRUE)		//将四则混合运算字符串交由RPN来完成解析
         {
            $arrArgCalc=$this->rpn_calc->toScratchJSON();	//生成符合Scratch3.0要求的数组数据
            //生成计算表达式

            $arrArgCalc[count($arrArgCalc)-1][1]=$dataArg[0][0];	//将最后一块积木的UID设置为当前UID
            $childBlockParent=isset($this->arrLogicBlockParent[$dataArg[0][0]])?$this->arrLogicBlockParent[$dataArg[0][0]]:$parentUID;	//子积木的parent

            $T=$this->parseCalculationExpression(Array('NUM','math_number','NUM'),$arrArgCalc,$childBlockParent,$dataArg[0][0]); //生成积木块，并将返回的最后一个积木的UID，替换原来的算数表达式。
            $arrLogicBlock[2]=$T[0];
         }
         //return NULL;
      }

      $arrChildBlockUID=Array($this->uid(),$this->uid());	//逻辑表达式是额外的积木块，所以UID由当前生成，并返回到调用处
      $arrShadowUID=Array($this->uid(),$this->uid());
      $arrLogicArgUID=Array('','');				//逻辑表达式的参数UID。逻辑表达式没有默认值，所以block和shadow合在一处
      $thisUID=$this->uid();					//条件表达式是由if等引出的，所以thisUID由自己生成。

      $arrLogicOptToInfo=Array(					//运算符与积木名称的对应关系
        '>'  => Array("operator_gt",    true,  true  ),		//	Array(积木名称，是否需要shadow，是否二目操作);
        '<'  => Array("operator_lt",    true,  true  ),
        '==' => Array("operator_equals",true,  true  ),
        '!=' => Array("operator_not",   false, true  ),
        '&&' => Array("operator_and",   false, true  ),
        '||' => Array("operator_or",    false, true  ),
        '!'  => Array("operator_not",   false, false )
      );

      $childLogicBlockUID='';

      if(is_array($dataArg))					//当前参数是被拆分后的结果
      {
         //$this->arrLogicBlockParent=Array();			//算术运算可在局部内完成，逻辑运算需要全局控制

         for($k=count($dataArg)-1;$k>=0;$k--)			//里面保存了已经解析拆分后的逻辑表达数据
         {
            $arrLogicBlock=$dataArg[$k];				//当前运算操作符：Array(逻辑运算符,UID,第一个参数,第二个参数);
            //var_dump($arrLogicBlock);

            //echo "arrLogicBlock\r\n";
            if($childLogicBlockUID=='')
            {
               //var_dump($arrLogicBlock);
               $childLogicBlockUID=$arrLogicBlock[1];		//倒序处理后，应该返回这个值
            }
            $childBlockParent=isset($this->arrLogicBlockParent[$arrLogicBlock[1]])?$this->arrLogicBlockParent[$arrLogicBlock[1]]:$parentUID;	//子积木的parent

            //获取当前积木的操作信息
            $arrLogicOptInfo=isset($arrLogicOptToInfo[$arrLogicBlock[0]])?$arrLogicOptToInfo[$arrLogicBlock[0]]:NULL;//运算操作符所对应的积木名称。原本用switch做，但用数组会更快。
            if($arrLogicOptInfo==NULL) break;					//未定义的运算符，数据错误，终止当前循环

            //参数处理。如果是二目操作，需要采集两个参数，否则采集一个。
            $arrArgVal=Array($arrLogicBlock[2]);			//逻辑表达式至少有一个参数。
            if($arrLogicOptInfo[2])					//如果为true，则有两个参数。
               array_push($arrArgVal,$arrLogicBlock[3]);                //也可以：$arrArgVal=Array($arrLogicBlock[2],$arrLogicBlock[3]);

            //准备数据
            //常量和变量以及非ID的数据，都是要有shadow的；
            //ID的数据，不需要shadow。

            //处理参数一中可能存在的算术表达式
            if(is_numeric($arrArgVal[0]))				//参数是数字，创建一个同值的shadow，由于是数字，所以不可能是不需要shadow的且或非
            {
               //echo "数字\n";
               $arrShadowUID[0]=$arrLogicArgUID[0]=$this->uid();	//给shadow生成一个UID。
               array_push($this->Blockly, '{"id": "'.$arrLogicArgUID[0].'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.$arrLogicBlock[2].'"}},"next": null,"topLevel": false,"parent": "'.$arrLogicBlock[1].'","shadow": true}');
               $arrLogicBlock[2]=$arrLogicArgUID[0];
            }
            else							//参数非数字
            {
               $arrLogicArgUID[0]=$this->uid();				//逻辑表达式第一个参数的UID

               if(in_array($arrArgVal[0],$this->arrVariables) )		//参数是已定义的变量，生成该变量的积木块，并将积木块UID替换原变量名，另外还要生成一个shadow
               {
                  //echo "变量1\n";
                  //变量积木
                  array_push($this->Blockly,    '{"id": "'.$arrLogicArgUID[0].'","opcode": "data_variable","inputs": {},"fields": {"VARIABLE": {"name": "VARIABLE","id": "'.$this->uid().'","value": "'.$arrLogicBlock[2].'","variableType": ""}},"next": null,"topLevel": false,"parent": "'.$arrLogicBlock[1].'","shadow": false}');
                  $arrLogicBlock[2]=$arrLogicArgUID[0];

                  $arrShadowUID[0]=$this->uid();
                  array_push($this->Blockly, '{"id": "'.$arrShadowUID[0].'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "50"}},"next": null,"topLevel": false,"parent": null,"shadow": true}');
               }

               else if(isset($this->arrSelfDefinedArgs[$this->arrCurrentBlock][$arrArgVal[0]]))	//对自制积木中的本地变量直接引用
               {
                  //echo "自制积木的变量\n";

                  //自制积木定义里对参数的调用，只需要：
                  //    VALUE的value为参数名。
                  
                  $arrLogicBlock[2]=$this->uid();//原本传入的是变量名，需要重新生成UID。
                  array_push($this->Blockly, '{"id": "'.$arrLogicBlock[2].'","opcode":"argument_reporter_boolean","inputs": {},"fields": {"VALUE": {"name": "VALUE","value": "'.$arrArgVal[0].'"}},"next": null,"topLevel": false,"parent":  "'.$arrLogicBlock[1].'","shadow":false}');
               }

               else if(preg_match("/ID_([^^]*?)_DI/",$arrArgVal[0])!=1)	//检测是否是积木块UID。“ID_xxxxxxxxxxxxxxxxxxxx_DI”为两个RPN所独有。
               {							//如果是积木块UID，不需要任何转换；如果不是，就需要通过RPN进行解析，因为当前是计算表达式。
                  //解析计算表达式
                  if($this->rpn_calc->init($arrArgVal[0])==TRUE)		//将四则混合运算字符串交由RPN来完成解析
                  {
                     $arrArgCalc=$this->rpn_calc->toScratchJSON();	//生成符合Scratch3.0要求的数组数据
                     //生成计算表达式
                     $T=$this->parseCalculationExpression(Array('NUM','math_number','NUM'),$arrArgCalc,$thisUID);//,$arrChildBlockUID); //生成积木块，并将返回的最后一个积木的UID，替换原来的算数表达式。
                     $arrLogicBlock[2]=$T[0];
                  }
                  //积木块不需要shadow
               }
               else
                  $this->arrLogicBlockParent[$arrLogicBlock[2]]=$arrLogicBlock[1];	//保存child与parent的映射关系
               $arrChildBlockUID[0]=$arrLogicBlock[2];			//将算术运算符最后一块积木的UID替换原参数。
            }

            //处理参数二中可能存在的算术表达式
            if($arrLogicOptInfo[2])//isset($arrArgVal[1]))
            {
               if(is_numeric($arrArgVal[1]))				//参数是数字，创建一个同值的shadow
               {
                  $arrShadowUID[1]=$arrLogicArgUID[1]=$this->uid();
                  array_push($this->Blockly, '{"id": "'.$arrLogicArgUID[1].'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.$arrLogicBlock[3].'"}},"next": null,"topLevel": false,"parent": "'.$arrLogicBlock[1].'","shadow": true}');
                  $arrLogicBlock[3]=$arrLogicArgUID[1];
               }
               else							//参数非数字
               {
                  $arrLogicArgUID[1]=$this->uid();				//逻辑表达式第一个参数的UID
                  if(in_array($arrArgVal[1],$this->arrVariables) )		//参数是已定义的变量，生成该变量的积木块，并将积木块UID替换原变量名，另外还要生成一个shadow
                  {
                     //echo "变量2\n";
                     //变量积木
                     array_push($this->Blockly,    '{"id": "'.$arrLogicArgUID[1].'","opcode": "data_variable","inputs": {},"fields": {"VARIABLE": {"name": "VARIABLE","id": "'.$this->uid().'","value": "'.$arrLogicBlock[3].'","variableType": ""}},"next": null,"topLevel": false,"parent": "'.$arrLogicBlock[1].'","shadow": false}');
                     $arrLogicBlock[3]=$arrLogicArgUID[1];

                     $arrShadowUID[1]=$this->uid();
                     array_push($this->Blockly, '{"id": "'.$arrShadowUID[1].'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "50"}},"next": null,"topLevel": false,"parent": null,"shadow": true}');
                  }

                  else if(isset($this->arrSelfDefinedArgs[$this->arrCurrentBlock][$arrArgVal[1]]))	//对自制积木中的本地变量直接引用
                  {
                     //echo "自制积木的变量\n";
                     $arrLogicBlock[3]=$this->uid();
                     array_push($this->Blockly, '{"id": "'.$arrLogicBlock[3].'","opcode":"argument_reporter_boolean","inputs": {},"fields": {"VALUE": {"name": "VALUE","value": "'.$arrArgVal[1].'"}},"next": null,"topLevel": false,"parent":  "'.$arrLogicBlock[1].'","shadow":false}');
                  }

                  else if(preg_match("/ID_([^^]*?)_DI/",$arrArgVal[1])!=1)	//检测是否是积木块UID。“ID_xxxxxxxxxxxxxxxxxxxx_DI”为两个RPN所独有。
                  {							//如果是积木块UID，不需要任何转换；如果不是，就需要通过RPN进行解析，因为当前是计算表达式。
                     //解析计算表达式
                     if($this->rpn_calc->init($arrArgVal[1])==TRUE)		//将四则混合运算字符串交由RPN来完成解析
                     {
                        if($arrArgCalc=$this->rpn_calc->toScratchJSON())	//生成符合Scratch3.0要求的数组数据
                        {
                           //生成计算表达式
                           $T=$this->parseCalculationExpression(Array('NUM','math_number','NUM'),$arrArgCalc,$thisUID);//,$arrChildBlockUID); //生成积木块，并将返回的最后一个积木的UID，替换原来的算数表达式。
                           $arrLogicBlock[3]=$T[0];
                        }
                     }
                  }
                  else
                     $this->arrLogicBlockParent[$arrLogicBlock[3]]=$arrLogicBlock[1];	//保存child与parent的映射关系
               
                  $arrChildBlockUID[1]=$arrLogicBlock[3];			//将算术运算符最后一块积木的UID替换原参数。
               }
            }

            //前面处理完了，后面开始生成积木块。
            //创建逻辑运算积木块

            //且或非不需要ShadowBlock
            if($arrLogicOptInfo[0]=="operator_not")			//非有两个操作格式，一个双目，一个单目。
            {
               if($arrLogicOptInfo[2])					//双目操作，也就是格式为：a!=b  //这个是明确为不等于!=，它还可以不大于，不小于，则由else来实现。
               {
                  $childEqualsBlockUID=$this->uid();

                  if(is_numeric($arrLogicBlock[2]))			//如果是数字，生成
                  {
                     $childEqualsArg1=$this->uid();
                     array_push($this->Blockly,'{"id": "'.$childEqualsArg1.'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.$arrLogicBlock[2].'"}},"next": null,"topLevel": false,"parent": "'.$childEqualsBlockUID.'","shadow": true}');
                  }
                  else							//如果非数字，表示前面已经生成过shadow了，现在直接使用。
                     $childEqualsArg1=$arrLogicBlock[2];

                  if(is_numeric($arrLogicBlock[3]))
                  {
                     $childEqualsArg2=$this->uid();
                     array_push($this->Blockly,'{"id": "'.$childEqualsArg2.'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.$arrLogicBlock[3].'"}},"next": null,"topLevel": false,"parent": "'.$childEqualsBlockUID.'","shadow": true}');
                  }
                  $childEqualsArg2=$arrLogicBlock[3];
                  array_push($this->Blockly,'{"id": "'.$childEqualsBlockUID.'","opcode": "operator_equals","inputs": {"OPERAND1": {"name": "OPERAND1","block": "'.$childEqualsArg1.'","shadow": "'.$childEqualsArg1.'"},"OPERAND2": {"name": "OPERAND2","block": "'.$childEqualsArg2.'","shadow": "'.$childEqualsArg2.'" }},"fields": {},"next": null,"topLevel": false,   "parent":"'.$arrLogicBlock[1].'","shadow": false}');

                  array_push($this->Blockly,'{"id": "'.$arrLogicBlock[1].'","opcode": "operator_not","inputs": {"OPERAND": {"name": "OPERAND","block": "'.$childEqualsBlockUID.'","shadow": null}  },"fields": {},"next": null,"topLevel": false,   "parent": "'.$childBlockParent.'","shadow": false}');

               }
               else//单目操作，也就是格式为：!a 
               {
                  array_push($this->Blockly,'{"id": "'.$arrLogicBlock[1].'","opcode": "operator_not","inputs": {"OPERAND": {"name": "OPERAND","block": "'.$arrLogicBlock[2].'","shadow": null}  },"fields": {},"next": null,"topLevel": false,   "parent": "'.$childBlockParent.'","shadow": false}');
               }
            }
            else //大于、小于、等于、且、或
            {
               array_push($this->Blockly,'{"id": "'.$arrLogicBlock[1].'","opcode": "'.$arrLogicOptInfo[0].'","inputs": {"OPERAND1": {"name": "OPERAND1","block": "'.$arrLogicBlock[2].'","shadow": '.(($arrLogicOptInfo[1])?'"'.$arrShadowUID[0].'"':'null').'},"OPERAND2": {"name": "OPERAND2","block": "'.$arrLogicBlock[3].'","shadow": '.(($arrLogicOptInfo[1])?'"'.$arrShadowUID[1].'"':'null').'}},"fields": {},"next": null,"topLevel": false,"parent": "'.$childBlockParent.'","shadow": false}');
            }
         }
      }
      else//非数组
      {
          echo "逻辑条件不存在\r\n";
          //逻辑条件里非数组，意味着异常，无数据。
      }
      //echo "return:".$childLogicBlockUID;
      return Array($childLogicBlockUID,$childLogicBlockUID);//$arrChildBlockUID;
   }


   /******************************************************************************
   *
   *    将由逆波兰算法解析出的结果，生成相应的积木块，并返回最顶层的积木块的ID。
   *
   *
   *    复杂的四则混合运算表达式，被拆成若干个由两个数组成的简单表达式，每个表达式，在这里被转换成对应的积木。
   *
   *    逆波兰算法，把最底层的算式，放在了最后，所以在生成时，需要倒过来处理。
   *
   *
   *    对非内置关键词的变量参数的解析。
   *    如：
   *        文本字符，数字，公式，变量。
   *        不符合要求的有：
   *        随机位置_random_,鼠标指针_mouse_,左右翻转等
   *
   *    $arrChildArgInfo：	Array(参数字段名INPUT/FIELD， 参数名STEPS/ANGLE/TEXT，数据类型math_number，参数类型NUM);//从$arrArgInfo里获取
   *    $dataArg：		积木块信息
   *    $parentuid:		上一块积木的UID
   *    返回：arrChildUID:     Array(参数1UID,参数2UID);	//实际只需要返回一个UID，shadow数据由主调积木块生成。
   *
   **********************************************************************************/
   private function parseCalculationExpression($arrChildArgInfo,$dataArg,$parentUID)
   {
      $arrChildBlockUID  = Array($this->uid(),$this->uid());	//参数如果是计算公式，则已被拆分成一个操作符加两个数的形式。
								//这两个数，需要额外生成两个积木控件，也就需要两个UID。
								//这两个主UID需要返回到主Block中。

      $arrCalcOptToName=Array(					//运算符与积木名称的对应关系
         '+'=>"operator_add",
         '-'=>"operator_subtract",
         '*'=>"operator_multiply",
         '/'=>"operator_divide"
      );

      $childCalcBlockUID='';

      if(is_array($dataArg))				//当前参数是数组，是计算表达式被拆分后的结果
      {
         $arrCalcBlockParent=Array();				//此处存放子积木块UID与父积木块UID的对应关系。

         for($k=count($dataArg)-1;$k>=0;$k--)
         {
            $arrCalcBlock=$dataArg[$k];				//从最后一组数据开始处理，每组数据格式为：Array('+',UID,1,2);

            if($childCalcBlockUID=='')				//最后一组数据，为整个运算表达式的总领，
               $childCalcBlockUID=$arrCalcBlock[1];		//需要把这组数据的UID返回给主调积木。

            $childBlockParent=isset($arrCalcBlockParent[$arrCalcBlock[1]])?$arrCalcBlockParent[$arrCalcBlock[1]]:$parentUID;	//子积木的parent

            $strCalcOptType=isset($arrCalcOptToName[$arrCalcBlock[0]])?$arrCalcOptToName[$arrCalcBlock[0]]:"";//运算操作符所对应的积木名称。原本用switch做，但用数组会更快。
            if($strCalcOptType=="") break;					//未定义的运算符，数据错误，终止当前循环

            $arrArgUID=Array($this->uid(),$this->uid());		//生成两个参数的block的UID
            $arrShadowUID=$arrArgUID;					//两个参数的shadow默认与block相同，不同就表示是变量或者其他积木。

            //准备数据
var_dump($this->arrSelfDefinedArgs);
            //参数1积木块
            if(is_numeric($arrCalcBlock[2]))				//纯数字参数，直接使用，不需要shadow。   UID 为 $arrArgUID[0]， ShadowID 与 UID 一致。
            {
               array_push($this->Blockly, '{"id": "'.$arrArgUID[0].'","opcode": "math_number"    ,"inputs": {},"fields": {"NUM": {"name": "NUM","value": "'.$arrCalcBlock[2].'"}},"next": null,"topLevel": false,"parent": "'.$arrCalcBlock[1].'","shadow": true}');
            }
            else
            {
               $arrShadowUID[0]=$this->uid();				//生成一个新的ShadowID
               if(in_array($arrCalcBlock[2],$this->arrVariables) )	//已定义变量，需要额外加一个Shadow,，ID 为 $arrArgUID[0] 和 $arrShadowUID[0]
               {
                  //block
                  array_push($this->Blockly, '{"id": "'.$arrArgUID[0].'","opcode": "data_variable","inputs": {},"fields": {"VARIABLE": {"name": "VARIABLE","id": "'.$this->uid().'","value": "'.$arrCalcBlock[2].'","variableType": ""}},"next": null,"topLevel": false,"parent": "'.$arrCalcBlock[1].'","shadow": false}');
                  //shadow
                  array_push($this->Blockly, '{"id": "'.$arrShadowUID[0].'","opcode": "math_number" ,"inputs": {},"fields": {"NUM": {"name": "NUM","value": ""}},"next": null,"topLevel": false,"parent":  "'.$arrCalcBlock[1].'","shadow": true}');
               }

               else if(isset($this->arrSelfDefinedArgs[$this->arrCurrentBlock][$arrCalcBlock[2]]))	//对自制积木中的本地变量直接引用
               {
                  echo "自制积木的变量：";

                  $arrCalcBlockParent[$arrCalcBlock[2]]=$this->arrSelfDefinedArgs[$this->arrCurrentBlock][$arrCalcBlock[2]];	//保存child与parent的映射关系

                  $arrArgUID[0]=$this->arrSelfDefinedArgs[$this->arrCurrentBlock][$arrCalcBlock[2]];//$arrCalcBlock[2];				//直接使用该ID
                  $arrShadowUID[0]=$this->uid();
                  //block对变量的直接引用
                  array_push($this->Blockly, '{"id": "'.$arrArgUID[0].'","opcode":"argument_reporter_string_number","inputs": {},"fields": {"VALUE": {"name": "VALUE","value": "'.$arrCalcBlock[2].'"}},"next": null,"topLevel": false,"parent":  "'.$arrCalcBlock[1].'","shadow":false}');
                  //shadow
                  array_push($this->Blockly, '{"id": "'.$arrShadowUID[0].'","opcode": "math_number" ,"inputs": {},"fields": {"NUM": {"name": "NUM","value": ""}},"next": null,"topLevel": false,"parent":  "'.$arrCalcBlock[1].'","shadow": true}');
               }

               else if(preg_match("/ID_([^^]*?)_DI/",$arrCalcBlock[2])==1)	//指向另一个积木，需要额外加一个Shadow
               {
                  $arrCalcBlockParent[$arrCalcBlock[2]]=$arrCalcBlock[1];	//保存child与parent的映射关系

                  $arrArgUID[0]=$arrCalcBlock[2];				//直接使用该ID
                  array_push($this->Blockly, '{"id": "'.$arrShadowUID[0].'","opcode":"math_number","inputs": {},"fields": {"NUM": {"name": "NUM","value": ""}},"next": null,"topLevel": false,"parent":  "'.$arrCalcBlock[1].'","shadow": true}');
               }

               else//啥也不是时，为0。此类情况，一般是在自制积木定义之外使用了参数变量，或者变量名错误。
               {
                  //echo "啥也不是\n";
                  array_push($this->Blockly, '{"id": "'.$arrShadowUID[0].'","opcode":      "math_number","inputs": {},"fields": {"NUM": {"name": "NUM","value": "0"}},"next": null,"topLevel": false,"parent": "'.$arrCalcBlock[1].'","shadow": true}');
                  $arrArgUID[0]=$arrShadowUID[0];$arrCalcBlock[3];//block和shadow保持一致
               }

            }

            //参数2积木块
            if(is_numeric($arrCalcBlock[3]))				//纯数字参数，直接使用，不需要shadow。
            {
               array_push($this->Blockly, '{"id": "'.$arrArgUID[1].'","opcode": "math_number","inputs": {},"fields": {"NUM": {"name": "NUM","value": "'.$arrCalcBlock[3].'"}},"next": null,"topLevel": false,"parent": "'.$arrCalcBlock[1].'","shadow": true}');
            }
            else
            {
               $arrShadowUID[1]=$this->uid();
               if(in_array($arrCalcBlock[3],$this->arrVariables) )	//已定义变量
               {
                  //echo "已定义变量\n";
                  array_push($this->Blockly, '{"id": "'.$arrArgUID[1].'","opcode": "data_variable","inputs": {},"fields": {"VARIABLE": {"name": "VARIABLE","id": "'.$this->uid().'","value": "'.$arrCalcBlock[3].'","variableType": ""}},"next": null,"topLevel": false,"parent": "'.$arrCalcBlock[1].'","shadow": false}');
                  array_push($this->Blockly, '{"id": "'.$arrShadowUID[1].'","opcode": "math_number","inputs": {},"fields": {"NUM": {"name": "NUM","value": ""}},"next": null,"topLevel": true,"parent": "'.$arrCalcBlock[1].'","shadow": true}');
               }

               else if(isset($this->arrSelfDefinedArgs[$this->arrCurrentBlock][$arrCalcBlock[3]]))	//对自制积木中的本地变量直接引用
               {
                  //还是需要加shadow
                  //echo "自制积木的变量：";
                  //var_dump($this->arrSelfDefinedArgs);
                  $arrCalcBlockParent[$arrCalcBlock[3]]=$this->arrSelfDefinedArgs[$this->arrCurrentBlock][$arrCalcBlock[3]];	//保存child与parent的映射关系

                  $arrArgUID[1]=$this->arrSelfDefinedArgs[$this->arrCurrentBlock][$arrCalcBlock[3]];//$arrCalcBlock[2];				//直接使用该ID
                  $arrShadowUID[1]=$this->uid();
                  //对变量的直接引用
                  array_push($this->Blockly, '{"id": "'.$arrArgUID[1].'","opcode":"argument_reporter_string_number","inputs": {},"fields": {"VALUE": {"name": "VALUE","value": "'.$arrCalcBlock[3].'"}},"next": null,"topLevel": false,"parent":  "'.$arrCalcBlock[1].'","shadow":false}');
                  //默认Shadow值
                  array_push($this->Blockly, '{"id": "'.$arrShadowUID[1].'","opcode": "math_number","inputs": {},"fields": {"NUM": {"name": "NUM","value": ""}},"next": null,"topLevel": false,"parent":  "'.$arrCalcBlock[1].'","shadow": true}');
               }

               else if(preg_match("/ID_([^^]*?)_DI/",$arrCalcBlock[3])==1)	//指向另一个积木
               {
                  //echo "另一块积木\n";
                  $arrCalcBlockParent[$arrCalcBlock[3]]=$arrCalcBlock[1];	//保存child与parent的映射关系

                  array_push($this->Blockly, '{"id": "'.$arrShadowUID[1].'","opcode":      "math_number","inputs": {},"fields": {"NUM": {"name": "NUM","value": ""}},"next": null,"topLevel": false,"parent": "'.$arrCalcBlock[1].'","shadow": true}');
                  $arrArgUID[1]=$arrCalcBlock[3];//block和shadow保持一致
               }

               else//啥也不是时，为0.
               {
                  //echo "错误数据\n";
                  array_push($this->Blockly, '{"id": "'.$arrShadowUID[1].'","opcode":"math_number","inputs": {},"fields": {"NUM": {"name": "NUM","value": "0"}},"next": null,"topLevel": false,"parent": "'.$arrCalcBlock[1].'","shadow": true}');
                  $arrArgUID[1]=$arrShadowUID[1];//$arrCalcBlock[3];//block和shadow保持一致
               }

            }
            //创建算术计算积木块
            //上面添加的都是参数的shadow，这里才是操作符积木块。
            array_push($this->Blockly,    '{"id": "'.$arrCalcBlock[1].'","opcode": "'.$strCalcOptType.'","inputs": {"NUM1": {"name": "NUM1","block": "'.$arrArgUID[0].'","shadow": "'.$arrShadowUID[0].'"},"NUM2": {"name": "NUM2","block": "'.$arrArgUID[1].'","shadow": "'.$arrShadowUID[1].'"}},"fields": {},"next": null,"topLevel": false,"parent": "'.$childBlockParent.'","shadow": false}');
         }

         //这个是shadow
         //有没有，没关系？？？？？？？？？？？？？？
         //array_push($this->Blockly,        '{"id": "'.$arrChildBlockUID[1].'", "opcode": "'.$arrChildArgInfo[1].'", "inputs": {}, "fields": { "'.$arrChildArgInfo[2].'": { "name": "'.$arrChildArgInfo[2].'", "value": "'.$defaultVAL.'" } }, "next": null, "topLevel": false, "parent": "'.$parentUID.'", "shadow": true}' );

         return  Array($childCalcBlockUID,$childCalcBlockUID);	//计算表达式，只要返回顶部积木块的block即可，shadow与block相同。
      }
      else							//如果$dataArg非数组，则它就是一个普通的常量/变量
      {
         $dataArg2=trim($dataArg,'"');


         if(in_array($dataArg,$this->arrVariables))					//对变量直接引用
         {
             //echo "引用变量，需要补shadow.";
             //block
             array_push($this->Blockly, '{"id": "'.$arrChildBlockUID[0].'","opcode": "data_variable","inputs": {},"fields": {"VARIABLE": {"name": "VARIABLE","id": "'.$this->uid().'","value": "'.$dataArg2.'","variableType": ""}},"next": null,"topLevel": false,"parent": "'.$parentUID.'","shadow": false}');
             //shadow
             array_push($this->Blockly, '{"id": "'.$arrChildBlockUID[1].'", "opcode": "math_number","inputs": {},"fields": {"NUM": {"name": "NUM","value": "10"}},"next": null,"topLevel": false,"parent": "'.$parentUID.'","shadow": true}');
         }
         else if(isset($this->arrSelfDefinedArgs[$this->arrCurrentBlock][$dataArg]))	//对自制积木中的本地变量直接引用
         {
             //echo "自制积木的变量，需要补shadow.";
             //block
             array_push($this->Blockly, '{"id": "'.$arrChildBlockUID[0].'","opcode": "argument_reporter_string_number","inputs": {},"fields": {"VALUE": {"name": "VALUE","value": "'.$dataArg2.'"}},"next": null,"topLevel": false,"parent": "'.$parentUID.'","shadow": false}');
             //shadow
             array_push($this->Blockly, '{"id": "'.$arrChildBlockUID[1].'", "opcode": "math_number","inputs": {},"fields": {"NUM": {"name": "NUM","value": "10"}},"next": null,"topLevel": false,"parent": "'.$parentUID.'","shadow": true}');

         }
         else{										//纯数字/字符串
             //echo "纯数字、字符串，不需要补shadow。";
             if($dataArg2==$dataArg)
               $dataArg=intval($dataArg);
             else
               $dataArg=$dataArg2;

             //block
             array_push($this->Blockly, '{"id": "'.$arrChildBlockUID[0].'", "opcode": "math_number","inputs": {},"fields": {"NUM": {"name": "NUM","value": "'.$dataArg.'"}},"next": null,"topLevel": false,"parent": "'.$parentUID.'","shadow": true}');
             $arrChildBlockUID[1]=NULL;
         }

         return  $arrChildBlockUID;//Array( 0=>block, 1=>shadow );
      }
   }

   /**********************************************************
   *
   **  拆分解析函数的参数（如果参数是公式，需要调用RPN来处理）
   **
   **  bLAST   true:最后一条数据，nextuid为空
   *
   *   传入单条记录
   ***********************************************************/
   private function parseArg( $arrFunc ,$bLAST=false)
   {
      if(!isset($arrFunc[0])) return NULL;

      //$nextUID=$bLAST?'null':$this->uid();
      $nFuncCount=count($arrFunc);

      if($bLAST) $nextUID='null';

      else      $nextUID=$this->uid();

      $this->nCURRENT++;

      $thisUID=array_pop($this->UIDS);
      if($thisUID=='null')  {$thisUID=$this->uid();$parentUID=NULL;}

      $parentUID=array_pop($this->UIDS);

      //if($bFIRST==true) $parentUID='null';
//if($bLAST) 
//      array_push($this->UIDS,'null');
//else
      array_push($this->UIDS,$thisUID);
      array_push($this->UIDS,$nextUID);

      switch($arrFunc[0])
      {
         //主调函数的处理方法
         //格式：funName(arg);

         //参数需要在自己积木里添加
         case "looks_gotofrontback":			//上移/下移
            array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "looks_gotofrontback","inputs": {},"fields": {"FRONT_BACK": {"name": "FRONT_BACK","value": "'.trim($arrFunc[2],"\"").'"}},"next": "'.$nextUID.'","topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID!=''?("\"".$parentUID."\""):"null").',"shadow": false}');
         break;


      	 /***********************带参数函数，需要处理inputs和fields*************************/
          case "motion_setrotationstyle":		//设置旋转方式			//一个不需要额外参数的特例
            array_push($this->Blockly,'{"id": "'.$thisUID.'", "opcode": "motion_setrotationstyle","inputs": {},"fields": {"STYLE": {"name": "STYLE","value": "'.trim($arrFunc[2],"\"").'"}}, "next": "'.$nextUID.'","topLevel": '.$this->bTOPLEVEL.' ,"parent": '.($parentUID!=''?("\"".$parentUID."\""):"null").',"shadow": false}');
         break;

         case "looks_changeeffectby":			//修改特效值为
            $childuid=$this->uid();
            array_push($this->Blockly,' {"id": "'.$thisUID.'","opcode": "'.$arrFunc[0].'","inputs": {    "CHANGE": {"name": "CHANGE","block": "'.$childuid.'","shadow": "'.$childuid.'"}},"fields": {"EFFECT": {"name": "EFFECT","value": "'.trim($arrFunc[2],'"').'"}},"next": null,"topLevel": true,"parent": null,"shadow": false    }');
            array_push($this->Blockly,' {"id": "'.$childuid.'","opcode": "math_number","inputs": {},"fields": {    "NUM": {"name": "NUM","value": "'.trim($arrFunc[4],'"').'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
         break;

         case "looks_seteffectto":			//设置特效为
            $childuid=$this->uid();
            array_push($this->Blockly,' {"id": "'.$thisUID.'","opcode": "'.$arrFunc[0].'","inputs": {    "VALUE": {"name": "VALUE","block": "'.$childuid.'","shadow": "'.$childuid.'"}},"fields": {    "EFFECT": {"name": "EFFECT","value": "'.trim($arrFunc[2],'"').'"}},"next": null,"topLevel": true,"parent": null,"shadow": false    }');
            array_push($this->Blockly,' {"id": "'.$childuid.'","opcode": "math_number","inputs": {},"fields": {    "NUM": {"name": "NUM","value": "'.trim($arrFunc[4],'"').'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
         break;

         //变量
         case "data_changevariableby":			//修改变量值为
            $childBlockUID=$this->uid();
            array_push($this->Blockly,'{"id": "'.$childBlockUID.'","opcode": "math_number","inputs": {},"fields": {    "NUM": {"name": "NUM","value": "'.$arrFunc[3].'"    }},"next": null,"topLevel": false,       "parent": "'.$thisUID.'","shadow": true    }');
            array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_changevariableby","inputs": {    "VALUE": {"name": "VALUE","block": "'.$childBlockUID.'","shadow": "'.$childBlockUID.'"    }},"fields": {    "VARIABLE": {"name": "VARIABLE","id": "'.$this->uid().'","value": "'.$arrFunc[2].'",       "variableType": ""    }},"next":'.($nextUID?'"'.$nextUID.'"':'null').',"topLevel":  '.$this->bTOPLEVEL.',"parent": null,"shadow": false    }');
         break;

         //变量
         case "data_setvariableto":			//设置变量值为
            $childBlockUID=$this->uid();
            array_push($this->Blockly,'{"id": "'.$childBlockUID.'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.trim($arrFunc[3],"\"").'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
            array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_setvariableto","inputs": {"VALUE": {"name": "VALUE","block": "'.$childBlockUID.'","shadow": "'.$childBlockUID.'"}},"fields": {"VARIABLE": {"name": "VARIABLE","id": "'.$this->uid().'","value": "'.$arrFunc[2].'","variableType": ""}},"next": '.($nextUID?'"'.$nextUID.'"':'null').',"topLevel": '.$this->bTOPLEVEL.',"parent": null,"shadow": false}');
         break;
         
         case  "data_showvariable":			//显示变量
            array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_showvariable","inputs": {},"fields": {"VARIABLE": {id:"'.$this->uid().'" "name": "VARIABLE","value": "'.trim($arrFunc[3],"\"").'", "variableType": ""}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": false}');
         break;


      	 /***********************不带参数和带数字参数函数*************************/
         //运动
         case "motion_movesteps":			//移动n步
         case "motion_turnright":			//向右转
         case "motion_turnleft":			//向左转
         case "motion_changexby":			//将X坐标增加n
         case "motion_changeyby":			//将Y坐标增加n
         case "motion_setx":				//将X坐标设为
         case "motion_sety":				//将Y坐标设为
         case "motion_pointindirection":		//面向n°方向
         case "motion_glidesecstoxy":			//n秒内滑行到xy
         case "motion_gotoxy":				//将Y坐标设为
         case "motion_goto":				//将Y坐标设为
         case "motion_glideto":
         case "motion_pointtowards":
         case "motion_ifonedgebounce":			//遇到边缘就反弹

         //外观
         case "looks_say":				//说
         case "looks_changesizeby":			//大小增加
         case "looks_setsizeto":			//修改大小为
         case "looks_think":				//想
         case "looks_sayforsecs":			//说n秒
         case "looks_thinkforsecs":			//想n秒
         case "looks_switchcostumeto":			//修改造型为
         case "looks_costume":				//造型
         case "looks_switchbackdropto":			//修改背景为
         case "looks_backdrops":			//背景
         case "looks_show":				//显示
         case "looks_hide":				//隐藏
         case "looks_cleargraphiceffects":		//清除图像特效
         case "looks_nextcostume":			//下一个造型
         case "looks_nextbackdrop":			//下一个背景

         //声音
         case "sound_stopallsounds":			//停止所有声音
         case "sound_cleareffects":			//清除音效

         //控制
         case "control_wait":				//等待
         /////case "control_repeat":			//重复
         case "control_delete_this_clone":		//删除此克隆体

         //侦测
         case "sensing_distanceto":			//到目标的距离
         case "sensing_touchingcolor":			//碰到颜色
         case "sensing_coloristouchingcolor":		//颜色碰到颜色
         case "sensing_resettimer":			//计时器归零
         case "sensing_mousedown":			//鼠标是否按下
         //运算
         //自制积木

         //画笔
         case "pen_setPenColorToColor":			//设置画笔颜色为
         case "pen_changePenColorParamBy":		//修改画笔参数
         case "pen_stamp":				//图章
         case "pen_penDown":				//落笔
         case "pen_penUp":				//抬笔

         //变量

         //自制扩展
         case "chattingroom_sendReport":		//上报信息

            //主积木数据的开头部分
            $strBlock='{"id": "'.$thisUID.'","opcode": "'.$arrFunc[0].'", "inputs": {';		//这里是边解析边拼接

            //对积木的参数进行处理

            //如果有参数，就进行处理；无参数，则忽略。
            $arrChildArg=$this->getArgName($arrFunc[0]);		//获取当前积木块的参数的配置信息
            $nCAC=count($arrChildArg);					//计算参数的个数

            if($nCAC>0)								//如果此积木有参数，就添加参数
            {
               //拼接当前积木的参数
               $arrArguments=Array();						//之前拆分后，由公式组成的参数，会被拆分成多个数据，需要重新拼接在一起
               $nArgumentCount=0;
               $nBraceCounter=1;
               $n=2;
               while($n<$nFuncCount)						//按“,”拆分参数
               {
                   if($arrFunc[$n]=='(') $nBraceCounter++;
                   else if($arrFunc[$n]==')') $nBraceCounter--;

                   if($nBraceCounter==0) break;
                   if($arrFunc[$n]==',') $nArgumentCount++;

                   else if(isset($arrArguments[$nArgumentCount])) $arrArguments[$nArgumentCount].=$arrFunc[$n];	//拼接同一个参数的多个数据。
                   else
                   {
                      $arrArguments[$nArgumentCount]=$arrFunc[$n];
                   }
                   $n++;
               }

               //对每个参数进行细分。参数可能是纯数字、字符串、变量和计算表达式
               $argArr=Array();
               for($i=0;$i<=$nArgumentCount;$i++)
               {
                  if(!is_numeric($arrArguments[$i]))				//非纯数字的参数，利用RPN算法进行分解。
                  {
                     $arg=NULL;
                     if($this->rpn_calc -> init($arrArguments[$i]))		//将四则混合运算字符串交由RPN来完成解析
                        $arg=$this->rpn_calc->toScratchJSON();			//生成符合Scratch3.0要求的数组数据
                     if($arg==NULL) $argArr[$i]=$arrArguments[$i];		//如因括号不匹配之类的问题导致解析失败，则直接使用，因为可能是关键词。
                     else
                     {
                        $argArr[$i]=$arg;					//解析成功，返回经RPN解析后的四则不混合运算数据
                     }
                  }
                  else $argArr[$i]=trim($arrArguments[$i]);			//纯数字参数，注意去除空格。
               }

               //var_dump($argArr);
               //构建当前积木的完整数据
               for($i=0;$i<$nCAC;$i++)
               {
                  //生成参数积木，返回UID
                  $arrChildUID=$this->parseCalculationExpression($arrChildArg[$i],$argArr[$i],$thisUID); //解析的过程中，也会创建相应的积木数据，最终返回UID

                  //每个参数都要有Shadow，这个shadow不在parseCalculationExpression里创建。
                  $strShadowUID=$this->uid();

                  if($arrChildUID[1]!=NULL)
                  {
                     //补一个shadow
                     array_push($this->Blockly,    '{"id": "'.$strShadowUID.'","opcode": "math_number","inputs": {},"fields": {    "NUM": {"name": "NUM","value": "10"    }},"next": null,"topLevel": true,"parent": null,"shadow": true}');
                     //拼接主积木的参数数据
                     $strBlock.=($i>0?',':'') . ' "'.$arrChildArg[$i][0].'": { "name": "'.$arrChildArg[$i][0].'", "block": "'.$arrChildUID[0].'", "shadow": "'.$strShadowUID.'" }';
                  }
                  else
                  {
                     //拼接主积木的参数数据
                     $strBlock.=($i>0?',':'') . ' "'.$arrChildArg[$i][0].'": { "name": "'.$arrChildArg[$i][0].'", "block": "'.$arrChildUID[0].'", "shadow": "'.$arrChildUID[0].'" }';

                  }
               }
            }

            //主积木数据的剩余部分
            $strBlock.='}, "fields": {}, "next": '.($nextUID!='null'?'"'.$nextUID.'"':$nextUID).', "topLevel": '.($parentUID!=NULL?'false':$this->bTOPLEVEL).', "parent": '.($parentUID==NULL?"null":"\"".$parentUID."\"").', "shadow": false}';

            //添加当前积木块数据
            array_push($this->Blockly,$strBlock);

         break;


         //事件
         //case "event_broadcast_menu":			//直接在event_broadcast里处理了。
         case "event_broadcast":			//广播消息
         case "event_broadcastandwait":			//广播消息

            $arrArguments=Array("","","");					//标准积木，最多三个参数，因为需要执行字符追加，所以得初始化。
            $nArgumentCount=0;
            //var_dump($arrFunc);
            //echo "ARRFUNC";
            for($i=2;$i<count($arrFunc)-2;$i++)
            {
               if($arrFunc[$i]==',') $nArgumentCount++;			//有逗号，就表示是多个参数
               else $arrArguments[$nArgumentCount].=$arrFunc[$i];
            }

            //var_dump($arrArguments);
            $argArr=Array();							//这里直接根据偏移量赋值，所以可以不用像$arrArguments那样进行初始化。
            for($i=0;$i<=$nArgumentCount;$i++)
            {
               if(!is_numeric($arrArguments[$i]))				//非纯数字的参数，利用RPN算法进行分解。
               {
                  $this->rpn_calc -> init($arrArguments[$i]);				//表达式的一些特殊情况（缺省乘号），由RPN2EXPRESSION类处理
                  $arg=$this->rpn_calc->toScratchJSON();
                  if($arg==FALSE) $argArr[$i]=$this->rpn_calc->getStrRPN();		//公式
                  else
                     $argArr[$i]=$arg;						//值
               }
               else $argArr[$i]=trim($arrArguments[$i]);			//纯数字参数，注意去除空格。
            }

            $childuid=$this->uid();

            array_push($this->Blockly,'{"id": "'.$childuid.'","opcode": "event_broadcast_menu","inputs": {},"fields": {"BROADCAST_OPTION": {"name": "BROADCAST_OPTION","id": "'.$this->uid().'","value": "'.trim($argArr[0],"\"").'","variableType": "broadcast_msg"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
            array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "'.$arrFunc[0].'","inputs": {"BROADCAST_INPUT": {"name": "BROADCAST_INPUT","block": "'.$childuid.'","shadow": "'.$childuid.'"}},"fields": {},"next": null,"topLevel": true,"parent": '.($parentUID==NULL?"null":"\"".$parentUID."\"").',"shadow": false}');

         break;


         default:					//其它特例

            //调用自制积木
            if(isset($this->arrSelfDefinedFunctions[$arrFunc[0]]))//确认当前函数名是否在自制积木列表中。
            {
               $this->arrCurrentBlock=$arrFunc[0];			//当前为

               $arrArgType=$this->arrSelfDefinedFunctions[$arrFunc[0]][1];	//TYPE
               $arrArgNames=$this->arrSelfDefinedFunctions[$arrFunc[0]][2];	//NAME

               $nCAC=count($arrArgType);

               //拼接当前积木的参数
               $arrArguments=Array();						//之前拆分后，由公式组成的参数，会被拆分成多个数据，需要重新拼接在一起
               $nArgumentCount=0;
               $nBraceCounter=1;
               $n=2;
               $arrArgData=Array();
               while($n<$nFuncCount)
               {
                   if($arrFunc[$n]=='(') $nBraceCounter++;
                   else if($arrFunc[$n]==')') $nBraceCounter--;

                   if($nBraceCounter==0) break;
                   if($arrFunc[$n]==',') $nArgumentCount++;

                   else if(isset($arrArguments[$nArgumentCount])) $arrArguments[$nArgumentCount].=$arrFunc[$n];	//拼接同一个参数的多个数据。
                   else
                   {
                      $arrArguments[$nArgumentCount]=$arrFunc[$n];
                   }
                   $arrArgData[$nArgumentCount][]=$arrFunc[$n];
                   $n++;
               }

               //对每个参数进行细分。参数可能是纯数字、字符串、变量和计算表达式
               $argArr=Array();
               for($i=0;$i<=$nArgumentCount;$i++)
               {
                  if(!is_numeric($arrArguments[$i]))				//非纯数字的参数，利用RPN算法进行分解。
                  {
                     $arg=NULL;
                     if($this->rpn_calc -> init($arrArguments[$i]))		//将四则混合运算字符串交由RPN来完成解析
                        $arg=$this->rpn_calc->toScratchJSON();			//生成符合Scratch3.0要求的数组数据
                     if($arg==NULL) $argArr[$i]=$arrArguments[$i];		//如因括号不匹配之类的问题导致解析失败，则直接使用，因为可能是关键词。
                     else
                     {
                        $argArr[$i]=$arg;					//解析成功，返回经RPN解析后的四则不混合运算数据
                     }
                  }
                  else $argArr[$i]=trim($arrArguments[$i]);			//纯数字参数，注意去除空格。
               }

               $input_str="";
               $argumentids_str="[";
               $arguments_str="[";
               $proccode_str="";
               $argumentdefaults="[";

               $arrArgName=Array();
               //$arrArgType=Array();

               $arrArgUID=Array();
               if(isset($this->arrSelfDefinedArgs[$this->arrCurrentBlock]))			//获取当前自制积木的参数信息
                  $arrArgUID=array_values($this->arrSelfDefinedArgs[$this->arrCurrentBlock]);	//去除所有的文本index，以数字为索引。

               //构建当前积木的完整数据
               for($j=0;$j<$nCAC;$j++)
               {
                  $arrChildUID=NULL;

                  if($arrArgType[$j]=="VAR")
                  {
                     //生成参数积木，返回UID
                     $arrChildUID=$this->parseCalculationExpression(Array('NUM','math_number','NUM'),$argArr[$j],$thisUID); //解析的过程中，也会创建相应的积木数据，最终返回UID
                  }
                  else
                  {
                     $arrBOOLChildUID=Array(NULL,NULL);

                     $arrProcedureBOOL=$this->rpn_logic->init($argArr[$j]);

                     $mpbCounter=count($arrProcedureBOOL);
                     for($mpb=0;$mpb<$mpbCounter;$mpb++)
                     {
                        if($mpb==0)
                           $arrBOOLChildUID=$this->parseLogicExpression($arrProcedureBOOL[$mpb],$thisUID);
                        else $this->parseLogicExpression($arrProcedureBOOL[$mpb],$arrBOOLChildUID[0]);
                     }
                     $arrChildUID=$arrBOOLChildUID;
                  }

                  //每个参数都要有Shadow，这个shadow不在parseCalculationExpression里创建。
                  $strShadowUID=$this->uid();

                  if($arrChildUID[1]!=NULL)
                  {
                     //如果是逻辑判断，不需要补这个shadow
                     if($arrArgType[$j]=="VAR")
                        array_push($this->Blockly,    '{"id": "'.$strShadowUID.'","opcode": "math_number","inputs": {},"fields": {    "NUM": {"name": "NUM","value": "10"    }},"next": null,"topLevel": true,"parent": null,"shadow": true}');
                     //拼接主积木的参数数据
                     $strBlock.=($j>0?',':'') . ' "'.$arrArgUID[$j].'": { "name": "'.$arrArgUID[$j].'", "block": "'.$arrChildUID[0].'", "shadow": "'.$strShadowUID.'" }';
                  }
                  else
                  {
                     //拼接主积木的参数数据
                     $strBlock.=($j>0?',':'') . ' "'.$arrArgUID[$j].'": { "name": "'.$arrArgUID[$j].'", "block": "'.$arrChildUID[0].'", "shadow": "'.$arrChildUID[0].'" }';
                     $strShadowUID=$arrChildUID[0];

                  }

                  if($j>0)
                  {
                     $proccode_str.="";
                     $input_str.=","; 
                     $argumentids_str.=",";
                     $arguments_str.=",";
                     $argumentdefaults.=",";
                  }

                  $arrArgName[$j]='_'.str_replace(" ","",$arrArgType[$j]).'_';	//积木proccode的拼接处理准备
                  $arrArgType[$j]=(($arrArgType[$j]=="VAR")?' %s ':' %b ');

                  $proccode_str	.=(($arrArgType[$j]=="VAR")?'%s':'%b');

                  $input_str	.='"'.$arrArgUID[$j].'": { "name": "'.$arrArgUID[$j].'", "block": "'.$arrChildUID[0].'", "shadow": "'.$strShadowUID.'"}';	//name和ID也必须为定义时使用的UID。//如果是布尔值，shadow为null
                  $argumentids_str	.='\"'.$arrArgUID[$j].'\"';  //调用时这里的UID应该是定义时的UID，这样才能把参数传递过去。
                  $arguments_str	.='\"'.$arrArgType[$j].'\"';
                  $argumentdefaults.=(($arrArgType[$j]=="VAR")?'\"\"':'\"false\"');
               }

               $argumentids_str.="]";
               $arguments_str.="]";
               //$proccode_str="";
               $argumentdefaults.="]";

               if($thisUID=="null")
               {
                  array_push($this->Blockly,'{"id": "'.$this->uid().'","opcode": "procedures_call","inputs": {'.$input_str.'},"fields": {},"next":  '.($nextUID=='null'?"null":"\"".$nextUID."\"").',"topLevel": true,"parent": null,"shadow": false,"mutation": {    "tagName": "mutation",    "children": [],    "proccode": "'.$this->arrSelfDefinedFunctions[$arrFunc[0]][0].'",    "argumentids": "'.$argumentids_str.'",    "warp": "false"}    }');
               }
               else
               {
                  array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "procedures_call","inputs": {'.$input_str.'},"fields": {},"next":  '.($nextUID=='null'?"null":"\"".$nextUID."\"").',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?"null":"\"".$parentUID."\"").',"shadow": false,"mutation": {    "tagName": "mutation",    "children": [],    "proccode": "'.$this->arrSelfDefinedFunctions[$arrFunc[0]][0].'",    "argumentids": "'.$argumentids_str.'",    "warp": "false"}    }');
                  //argumentids 					//这里ID要跟prototype的保持一致
               }
               return NULL;
            }//调用自制积木的处理结束。



            //对变量进行赋值操作的处理
            //如果变量未定义，则在代码执行后，会自动添加该变量，且该变量的类型为适用于所有角色。
            $arrTemp=explode("=",$arrFunc[0]);
            if(count($arrTemp)==2)				//有赋值操作
            {
               if($arrTemp[0][strlen($arrTemp[0])-1]=="+")      //+=
               {
                  array_push($this->UIDS,$thisUID);		//当前这一轮的thisUID已经被取出，但实际要到下一次调用时才使用，所以仍旧压回去。
                  $this->parseArg(Array("data_changevariableby","(",  trim(trim($arrTemp[0],'+')), trim($arrTemp[1]),")"));
               }
               else						//=
               {
                  array_push($this->UIDS,$thisUID);		//当前这一轮的thisUID已经被取出，但实际要到下一次调用时才使用，所以仍旧压回去。
                  $this->parseArg(Array("data_setvariableto","(",trim($arrTemp[0]), trim($arrTemp[1]),")"));
               }
            }
            else
            {
               $arrTemp=explode(".",$arrFunc[0]);
               //var_dump($arrTemp);
               if(count($arrTemp)==2)
               {
                  switch($arrTemp[1])
                  {
                     case "push":	//将东西加入
                        $argChildUID=$this->uid();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_addtolist","inputs": {"ITEM": {"name": "ITEM","block": "'.$argChildUID.'","shadow": "'.$argChildUID.'"}},"fields": {"LIST": {"name": "LIST","id": "'.$this->uid().'","value": "'.trim($arrTemp[0]).'","variableType": "list"}}, "next": '.($nextUID!='null'?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?"null":"\"".$parentUID."\"").',"shadow": false}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID.'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.trim($arrFunc[2],'"').'"}},"next": null,"topLevel": false,   "parent": "'.$thisUID.'","shadow": true}');

                     break;
                     case "delete":	//删除第n项
                        $argChildUID=$this->uid();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_deleteoflist","inputs": {"INDEX": {"name": "INDEX","block": "'.$argChildUID.'","shadow": "'.$argChildUID.'"}},"fields": {"LIST": {"name": "LIST","id": "'.$this->uid().'","value": "'.trim($arrTemp[0]).'","variableType": "list"}}, "next": '.($nextUID!='null'?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?"null":"\"".$parentUID."\"").',"shadow": false}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID.'","opcode": "math_integer","inputs": {},"fields": {"NUM": {"name": "NUM","value": "'.trim($arrFunc[2],'"').'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        
                     break;
                     case "removeAll":	//删除全部项目
                        $argChildUID=$this->uid();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_deletealloflist","inputs": {},"fields": {"LIST": {"name": "LIST","id": "'.$this->uid().'","value": "'.trim($arrTemp[0]).'","variableType": "list"}}, "next": '.($nextUID!='null'?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?"null":"\"".$parentUID."\"").',"shadow": false}');
                        
                     break;
                     case "insert":	//在第n项前插入
                        $argChildUID1=$this->uid();
                        $argChildUID2=$this->uid();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_insertatlist","inputs": {"INDEX": {"name": "INDEX","block": "'.$argChildUID1.'","shadow": "'.$argChildUID1.'"},"ITEM": {"name": "ITEM","block": "'.$argChildUID2.'","shadow": "'.$argChildUID2.'"}},"fields": {"LIST": {"name": "LIST","id": "'.$this->uid().'","value": "'.trim($arrTemp[0]).'","variableType": "list"}}, "next": '.($nextUID!='null'?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?"null":"\"".$parentUID."\"").',"shadow": false}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID1.'","opcode": "math_integer","inputs": {},"fields": {"NUM": {"name": "NUM","value": "'.trim($arrFunc[2]).'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID2.'","opcode": "text","inputs": {},"fields": {"TEXT": {   "name": "TEXT","value": "'.trim($arrFunc[4],'"').'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        
                     break;
                     case "replace":	//替换第n项数据
                        $argChildUID1=$this->uid();
                        $argChildUID2=$this->uid();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_replaceitemoflist","inputs": {"INDEX": {"name": "INDEX","block": "'.$argChildUID1.'","shadow": "'.$argChildUID1.'"},"ITEM": {"name": "ITEM","block": "'.$argChildUID2.'","shadow": "'.$argChildUID2.'"}},"fields": {"LIST": {"name": "LIST","id": "'.$this->uid().'","value": "'.trim($arrTemp[0]).'","variableType": "list"}}, "next": '.($nextUID!='null'?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?"null":"\"".$parentUID."\"").',"shadow": false}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID1.'","opcode": "math_integer","inputs": {},"fields": {"NUM": {"name": "NUM","value": "'.trim($arrFunc[2]).'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID2.'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.trim($arrFunc[4],'"').'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        
                     break;
                     case "getAt":	//第n项数据
                        $argChildUID=$this->uid();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_itemoflist","inputs": {"INDEX": {"name": "INDEX","block": "'.$argChildUID.'","shadow": "'.$argChildUID.'"}},"fields": {"LIST": {"name": "LIST","id": "'.$this->uid().'","value": "'.trim($arrTemp[0]).'","variableType": "list"}}, "next": '.($nextUID!='null'?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?"null":"\"".$parentUID."\"").',   "shadow": false,}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID.'","opcode": "math_integer","inputs": {},"fields": {"NUM": {"name": "NUM","value": "'.trim($arrFunc[2]).'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        
                     break;
                     case "indexOf":	//某个东西第一次出现的编号
                        $argChildUID=$this->uid();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_itemnumoflist","inputs": {"ITEM": {"name": "ITEM","block": "'.$argChildUID.'","shadow": "'.$argChildUID.'"}},"fields": {"LIST": {"name": "LIST","id": "'.$this->uid().'","value": "'.trim($arrTemp[0]).'","variableType": "list"}}, "next": '.($nextUID!='null'?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?"null":"\"".$parentUID."\"").',"shadow": false}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID.'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.trim($arrFunc[2],'"').'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        
                     break;
                     case "length":	//列表的项目数
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_lengthoflist","inputs": {},"fields": {"LIST": {"name": "LIST","id": "'.$this->uid().'","value": "'.trim($arrTemp[0]).'","variableType": "list"}}, "next": '.($nextUID!='null'?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?"null":"\"".$parentUID."\"").',"shadow": false}');
                        
                     break;
                     case "exist":	//列表是否包含东西
                        $argChildUID=$this->uid();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_listcontainsitem","inputs": {"ITEM": {"name": "ITEM","block": "'.$argChildUID.'","shadow": "'.$argChildUID.'"}},"fields": {"LIST": {"name": "LIST","id": "'.$this->uid().'","value": "'.trim($arrTemp[0]).'","variableType": "list"}}, "next": '.($nextUID!='null'?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?"null":"\"".$parentUID."\"").',"shadow": false}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID.'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.trim($arrFunc[2],'"').'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        
                     break;

                  }
               }
            }

            return NULL;
         //default
      }

      $this->bTOPLEVEL="false";
   }


   //检测字符串是否是积木的名称
   private function isBlocks($str)
   {
      return isset($this->arrArgInfo[$str]);
   }

   //检测参数类型，已废弃？
   private function checkArgType($argdata)//,$thisuid,$parentUID)
   {
      if(in_array($argdata,$this->arrVariables))
      {
         return true;

      }
      else return false;
   }

   //获取参数名
   private function getArgName($opcode)
   {
      return isset($this->arrArgInfo[$opcode])?$this->arrArgInfo[$opcode]:Array();
   }
}
?>
