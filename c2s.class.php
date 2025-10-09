<?php
//set_time_limit(3);
/*************************************************************************************************************************
1.加载json数据
2.获取所有积木块ID
3.获取所有头部积木块（HATs，此类积木块为每组代码的起始位置）
4.从头部积木块开始解析，并从所有积木块数组中，将已解析的积木块索引删除，剩余的积木块，即为零散积木块，在C中暂时可以舍弃，或者独立放置。
      解析时，需要遍历链表。


TODO：

motion_movesteps( 160 );
for(int i1 = 0; i1 < 10; i1++ ){
   motion_movesteps( 150 );
   for(int i2 = 0; i2 < 10; i2++ ){
      motion_movesteps( 103 );
   }
   motion_movesteps( 120 );
}
motion_movesteps( 110 );




//以下为已关联事件的积木
//当接收到广播
event_whenbroadcastreceived("跟上2"){
   looks_show();
   motion_pointindirection( data_itemoflist( 坐标2 ,  (  ( 编号 * 3 )  + 3 )  ) );
   motion_gotoxy( data_itemoflist( 坐标2 ,  (  ( 编号 * 3 )  + 1 )  ) , data_itemoflist( 坐标2 ,  (  ( 编号 * 3 )  + 2 )  ) );
   if(  ( 身长2 == 编号 )  ){
      control_delete_this_clone();
   }
}




Array
(
    [0] => Array
        (
            [0] => Array
                (
                    [0] => >
                    [1] => ID_}ve1]2EubQC:SIH}Oc4c_DI
                    [2] => ID_r;rMrM#;Kv~0K;Kqsz3u_DI + ID_tu55aF~L[Qg#S#?oQ.TO_DI		//这里少了一个算术运算分解
                    [3] => 10
                )

        )

    [2] => Array
        (
            [0] => Array
                (
                    [0] => operator_random
                    [1] => ID_r;rMrM#;Kv~0K;Kqsz3u_DI
                    [2] => 1,4
                )

            [1] => Array
                (
                    [0] => operator_random
                    [1] => ID_tu55aF~L[Qg#S#?oQ.TO_DI
                    [2] => 1,3
                )

        )

)


关于变量ID问题

当前文本代码转积木，是对当前角色的代码进行更新，其中变量部分，也有改动。
如果变量是仅适用于当前角色，那没问题，全部清掉全部重来；
如果变量是适用于所有角色的，那其他角色一旦也用了这个变量，就会造成异常：变量ID不一致。

解决方案：
文本代码提交时，也获取一份适用于所有角色的变量的ID，在代码生成时，直接用已有的ID，
这样，即使重新生成，也不会出现ID异常的情况。
这个最后再做。
备忘。



注意：
    如果一个字符串是逻辑表达式，则在处理后，即使再用算术表达式算法进行处理，也不会再出现函数调用了。


*************************************************************************************************************************/

define("DEBUGs", false);


/*************************************************************************************************************************

  此文件包含了：
     1.PHP版Scratch3.0的20字符ID生成器：function UIDS();

        此函数在“c2s.class.php”、“rpn_logic_expression.class.php”和“rpn_calc_expression.class.php”中被大量使用。


     2.算术/逻辑表达式预处理程序：function preProcessingFunctionCall($strExpression)

        用于从表达式中提取出函数调用信息。

*************************************************************************************************************************/
include_once "./preprocessing.inc.php";

include_once "./rpn_calc_expression.class.php";				//处理四则混合运算的逆波兰类定义
include_once "./rpn_logic_expression.class.php";			//处理逻辑运算的逆波兰类定义


/*************************************************************************************************************************
*
*   将类C文本转换成Scratch3.0的JSON数据
*
*************************************************************************************************************************/
class CToScratch3
{
   private  $rpn_calc	 		= NULL;				//处理四则混合运算的逆波兰类对象
   private  $rpn_logic	 		= NULL;				//处理逻辑运算的逆波兰类对象
   private  $arrCODEDATA 		= Array(NULL,NULL,NULL,NULL);	//客户端传过来的代码数据
   private  $Blockly	 		= Array();			//代码经解析后生成的积木块数据
   private  $UIDS 			= NULL;				//用来存储各个积木块间关系的堆栈
   private  $bTOPLEVEL   		= "true";			//逻辑是true|false，但用的时候要用文本，所以还是按布尔值类型命名。

   private  $arrSelfDefinedFunctions	= Array();			//自制积木的函数信息，包含了函数名，proccode，参数名和类型
   private  $arrSelfDefinedFunctionArgs = Array();			//保存自制积木的本地变量信息
   private  $arrVariableUIDS 		= Array();			//保存变量UID信息：变量名=>UID
   private  $arrBlockToParent 		= Array();			//存放拆分后的子积木块的映射关系

   private  $arrCurrentSDFBlock 	= "";				//当前自制积木的名字，用于访问指定自制积木的变量信息。

   private  $arrCalcFunctionToActuralOpcode=Array(					//算术运算符拆分后的数据中，操作符与积木opcode的映射关系
      '+'				=> Array('operator_add',		0),     //"运算符"=>Array(函数对应的积木的opcode,类型);
      '-'				=> Array('operator_subtract',		0),
      '*'				=> Array('operator_multiply',		0),
      '/'				=> Array('operator_divide',		0),
      '%'				=> Array('operator_mod',		0),

      'join'				=> Array('operator_join',		1),
      'random'				=> Array('operator_random',		1),	//0为普通计算
      'round'				=> Array('operator_round',		1),	//1为函数调用并返回数值
      'operator_length'			=> Array('operator_length',		1),
      'sensing_distanceto'		=> Array('sensing_distanceto',		1),
      'sensing_mousedown'		=> Array('sensing_mousedown',		1),
      'sensing_keypressed'		=> Array('sensing_keypressed',		1),
      'sensing_coloristouchingcolor'	=> Array('sensing_coloristouchingcolor',1),
      'sensing_touchingcolor'		=> Array('sensing_touchingcolor',	1),
      'sensing_touchingobject'		=> Array('sensing_touchingobject',	1),
      'sensing_timer'			=> Array('sensing_timer',		1),
      'sensing_answer'			=> Array('sensing_answer',		1),
      'operator_mathop'			=> Array('operator_mathop',		1),
      'looks_costumenumbername'		=> Array('looks_costumenumbername',	1),
      'looks_backdropnumbername'	=> Array('looks_backdropnumbername',	1),
      'looks_size'			=> Array('looks_size',			1),
      'motion_xposition'		=> Array('motion_xposition',		1),
      'motion_yposition'		=> Array('motion_yposition',		1),
      'motion_direction'		=> Array('motion_direction',		1),

      'abs'				=> Array('operator_mathop',		2),	//这些函数都是由operator_mathop积木实现的。
      'ceiling'				=> Array('operator_mathop',		2),
      'floor'				=> Array('operator_mathop',		2),
      'sqrt'				=> Array('operator_mathop',		2),
      'sin'				=> Array('operator_mathop',		2),
      'cos'				=> Array('operator_mathop',		2),
      'tan'				=> Array('operator_mathop',		2),
      'atan'				=> Array('operator_mathop',		2),
      'asin'				=> Array('operator_mathop',		2),
      'acos'				=> Array('operator_mathop',		2),
      'ln'				=> Array('operator_mathop',		2),
      'log'				=> Array('operator_mathop',		2),
      'e ^'				=> Array('operator_mathop',		2),
      '10 ^'				=> Array('operator_mathop',		2),

   );

   /*************************************************************************************************************************

      积木的参数的配置信息
      每个带参数的积木，都需要一个额外的积木来承载它的参数信息。

      配置信息格式：
          Array(
               "fields"=>Array(						//下拉菜单参数
                           Array("字段名1","参数1类型")				//此处“参数1类型”数据为可选。有些积木有多个参数，或者一个都没有。
                       ),
               "inputs"=>Array(						//接收从键盘输入的文本或数字参数，也可接收变量或计算公式。
                           Array("字段名2","参数2的opcode","参数2类型")		//有些积木有多个参数，或者一个都没有。
                       )
          )

   *************************************************************************************************************************/
   private  $arrArgumentConfig  = Array(

      //运动
      "motion_movesteps"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("STEPS","math_number","NUM"))),						//移动10步
      "motion_turnright"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("DEGREES","math_number","NUM"))),						//右转
      "motion_turnleft"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("DEGREES","math_number","NUM"))),						//左转
      "motion_goto"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("TO","motion_goto_menu","TO"))),						//移到
      "motion_gotoxy"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("X","math_number","NUM"),Array("Y","math_number","NUM"))),		//移到XY
      "motion_glideto" 				=>	Array("fields"=>Array(),"inputs"=>Array(Array("SECS","math_number","NUM"),Array("TO","motion_glideto_menu","TO"))),	//滑行到
      "motion_glidesecstoxy"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("SECS","math_number","NUM"),Array("X","math_number","NUM"),		//n秒内滑行到XY
						                                          Array("Y","math_number","NUM"))),
      "motion_pointindirection"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("DIRECTION","math_angle","NUM"))),					//面向
      "motion_pointtowards" 			=>	Array("fields"=>Array(),"inputs"=>Array(Array("TOWARDS","motion_pointtowards_menu","TOWARDS"))),			//面向目标
      "motion_changexby"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("DX","math_number","NUM"))),						//X坐标增加
      "motion_setx"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("X","math_number","NUM"))),						//X坐标设为
      "motion_changeyby"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("DY","math_number","NUM"))),						//Y坐标增加
      "motion_sety"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("Y","math_number","NUM"))),						//Y坐标设为
      "motion_ifonedgebounce"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//遇到边缘就反弹
      "motion_setrotationstyle" 		=>	Array("fields"=>Array(Array("STYLE")),"inputs"=>Array()),								//设置旋转方式

      "motion_xposition"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//x坐标
      "motion_yposition"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//y坐标	
      "motion_direction"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//方向	

      //外观
      "looks_sayforsecs" 			=>	Array("fields"=>Array(),"inputs"=>Array(Array("MESSAGE","text","TEXT"),Array("SECS","math_number","NUM"))),		//说几秒
      "looks_say"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("MESSAGE","text","TEXT"))),						//说
      "looks_thinkforsecs" 			=>	Array("fields"=>Array(),"inputs"=>Array(Array("MESSAGE","text","TEXT"),Array("SECS","math_number","NUM"))),		//想几秒
      "looks_think" 				=>	Array("fields"=>Array(),"inputs"=>Array(Array("MESSAGE","text","TEXT"))),						//想
      "looks_switchcostumeto" 			=>	Array("fields"=>Array(),"inputs"=>Array(Array("COSTUME","looks_costume","COSTUME"))),					//切换造型为
      "looks_nextcostume"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//下一个造型
      "looks_switchbackdropto" 			=>	Array("fields"=>Array(),"inputs"=>Array(Array("BACKDROP","looks_backdrops","BACKDROP"))),				//切换背景为
      "looks_nextbackdrop"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//下一个背景
      "looks_changesizeby"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("CHANGE","math_number","NUM"))),						//将大小增加
      "looks_setsizeto"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("SIZE","math_number","NUM"))),						//将大小设为
      "looks_changeeffectby"			=>	Array("fields"=>Array(Array("EFFECT")),"inputs"=>Array(Array("CHANGE","math_number","NUM"))),				//将特效增加
      "looks_seteffectto"			=>	Array("fields"=>Array(Array("EFFECT")),"inputs"=>Array(Array("VALUE","math_number","NUM"))),				//将特效设为
      "looks_cleargraphiceffects"		=>	Array("fields"=>Array(),"inputs"=>Array()),										//清除图像特效
      "looks_show"				=>	Array("fields"=>Array(),"inputs"=>Array()),										//显示
      "looks_hide"				=>	Array("fields"=>Array(),"inputs"=>Array()),										//隐藏
      "looks_goforwardbackwardlayers"		=>	Array("fields"=>Array(Array("FORWARD_BACKWARD","text")),"inputs"=>Array(Array("NUM","math_integer","NUM"))),		//上/下移一层
      "looks_gotofrontback"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("FRONT_BACK","text","TEXT"))),						//置于顶/底层
      "looks_costume" 				=>	Array("fields"=>Array(),"inputs"=>Array()),		//三个变量，待处理。
      "looks_backdrops" 			=>	Array("fields"=>Array(),"inputs"=>Array()),
      "looks_size" 				=>	Array("fields"=>Array(),"inputs"=>Array()),
      "looks_costumenumbername"			=>	Array("fields"=>Array(Array("NUMBER_NAME")),"inputs"=>Array()),
      "looks_backdropnumbername"		=>	Array("fields"=>Array(Array("NUMBER_NAME")),"inputs"=>Array()),

      //声音
      "sound_playuntildone"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("SOUND_MENU","sound_sounds_menu","SOUND_MENU"))),				//播放声音等待播完
      "sound_play"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("SOUND_MENU","sound_sounds_menu","SOUND_MENU"))),				//播放声音
      "sound_changeeffectby"			=>	Array("fields"=>Array(Array("EFFECT")),"inputs"=>Array(Array("VALUE","math_number","NUM"))),				//将音效增加
      "sound_seteffectto"			=>	Array("fields"=>Array(Array("EFFECT")),"inputs"=>Array(Array("VALUE","math_number","NUM"))),				//将音效设为
      "sound_changevolumeby"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("VOLUME","math_number","NUM"))),						//将音量增加
      "sound_setvolumeto"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("VOLUME","math_number","NUM"))),						//将音量设为
      "sound_volume"				=>	Array("fields"=>Array(),"inputs"=>Array()),										//音量
      "sound_sounds_menu"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//播放声音等待播完
      "sound_stopallsounds"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//停止所有声音
      "sound_cleareffects"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//清除音效

      //事件
      "event_whenflagclicked"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//当绿旗被点击
      "event_whenkeypressed"			=>	Array("fields"=>Array(Array("KEY_OPTION")),"inputs"=>Array()),										//当按键被点击
      "event_whenthisspriteclicked"		=>	Array("fields"=>Array(),"inputs"=>Array()),										//当角色被点击
      "event_whenbackdropswitchesto"		=>	Array("fields"=>Array(Array("BACKDROP")),"inputs"=>Array()),										//当背景切换到
      "event_whengreaterthan"			=>	Array("fields"=>Array(Array("WHENGREATERTHANMENU")),"inputs"=>Array(Array("VALUE","math_number","NUM"))),										//当值大于
      "event_whenbroadcastreceived"		=>	Array("fields"=>Array(Array("BROADCAST_OPTION","broadcast_msg")),"inputs"=>Array()),										//当接收到广播消息
      "control_start_as_clone"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//当克隆开始
      "event_whenstageclicked"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//当舞台被点击
      "event_broadcast" 			=>	Array("fields"=>Array(),"inputs"=>Array(Array("BROADCAST_INPUT","event_broadcast_menu","BROADCAST_OPTION","broadcast_msg"))),	//广播
      "event_broadcastandwait"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("BROADCAST_INPUT","event_broadcast_menu","BROADCAST_OPTION","broadcast_msg"))),	//广播消息

      //控制
      "control_stop"				=>	Array("fields"=>Array(Array("STOP_OPTION","text")),"inputs"=>Array()),		//停止
      "control_wait"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("DURATION","math_positive_number","NUM"))),				//等待
      /****************************↓********↓*****↓**已*经*在*主*程*序*中*实*现*，*不*需*要*这*些*配*置*信*息*了*。**↓****↓*****↓******↓****************************
      "control_forever"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("SUBSTACK","math_whole_number","NUM"),Array("SUBSTACK","",""))),		//等待<条件>
      "control_repeat_until"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("SUBSTACK","math_whole_number","NUM"),Array("SUBSTACK","",""))),		//重复执行直到
      "control_wait_until"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("SUBSTACK","math_whole_number","NUM"),Array("SUBSTACK","",""))),		//重复执行直到
      "control_repeat"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("TIMES","math_whole_number","NUM"),Array("SUBSTACK","",""))),		//重复执行n次
      "for"					=>	Array("fields"=>Array(),"inputs"=>Array()),										//这4个，仅在将代码按换行拆分时使用。
      "if"					=>	Array("fields"=>Array(),"inputs"=>Array()),
      "do"					=>	Array("fields"=>Array(),"inputs"=>Array()),
      "while"					=>	Array("fields"=>Array(),"inputs"=>Array()),
      *****************************↑********↑*****↑**已*经*在*主*程*序*中*实*现*，*不*需*要*这*些*配*置*信*息*了*。**↑****↑*****↑******↑****************************/
      "control_delete_this_clone"		=>	Array("fields"=>Array(),"inputs"=>Array()),										//删除此克隆体
      "control_create_clone_of"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("CLONE_OPTION","control_create_clone_of_menu","CLONE_OPTION"))),		//克隆

      //侦测
      "sensing_username"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//当前用户名
      "sensing_mousex"				=>	Array("fields"=>Array(),"inputs"=>Array()),										//鼠标X坐标
      "sensing_mousey"				=>	Array("fields"=>Array(),"inputs"=>Array()),										//鼠标Y坐标
      "sensing_mousedown"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//探测鼠标是否被按下
      "sensing_keypressed"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("KEY_OPTION","sensing_keyoptions","KEY_OPTION"))),			//探测某按键是否被按下
      "sensing_dayssince2000"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//自2000年开始至今的天数
      "sensing_loudness"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//响度
      //"sensing_keyoptions"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("KEY_OPTION","text","TEXT"))),						//按键
      "sensing_setdragmode"			=>	Array("fields"=>Array(Array("DRAG_MODE","text")),"inputs"=>Array()),							//设置角色是否允许被拖拽
      "sensing_distanceto"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("DISTANCETOMENU","sensing_distancetomenu","DISTANCETOMENU"))),		//到目标的距离
      "sensing_distancetomenu"			=>	Array("fields"=>Array(Array("DISTANCETOMENU","text")),"inputs"=>Array(Array("DISTANCETOMENU","text","TEXT"))),		//获取到目标的距离的菜单选项
      "sensing_answer"				=>	Array("fields"=>Array(),"inputs"=>Array()),										//询问的答案
      "sensing_askandwait"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("QUESTION","text","TEXT"))),						//询问并等待
      "sensing_timer"				=>	Array("fields"=>Array(),"inputs"=>Array()),										//定时器
      "sensing_touchingcolor" 			=>	Array("fields"=>Array(),"inputs"=>Array(Array("COLOR","colour_picker" ,"COLOUR"))),					//碰到颜色
      "sensing_coloristouchingcolor" 		=>	Array("fields"=>Array(),"inputs"=>Array(Array("COLOR","colour_picker" ,"COLOUR"),
						                                                Array("COLOR2","colour_picker" ,"COLOUR"))),					//颜色碰到颜色
      "sensing_touchingobject"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("TOUCHINGOBJECTMENU","sensing_touchingobjectmenu","TOUCHINGOBJECTMENU"))),//碰到对象
      //"sensing_touchingobjectmenu"		=>	Array("fields"=>Array(Array("TOUCHINGOBJECTMENU","text")),"inputs"=>Array(Array("TOUCHINGOBJECTMENU","sensing_touchingobjectmenu","TEXT"))),	//碰到对象的选项菜单
      "sensing_current"				=>	Array("fields"=>Array(Array("PROPERTY","text")),"inputs"=>Array(Array("CURRENTMENU","sensing_of_object_menu","TEXT"))),	//当前的年月日时分秒
      "sensing_of"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("OBJECT","text","TEXT"),Array("PROPERTY","text","TEXT"))),		//获取对象的某项参数
      "sensing_of_object_menu"			=>	Array("fields"=>Array(Array("OBJECT","text")),"inputs"=>Array(Array("OBJECT","text","TEXT"))),				//对象菜单
      "sensing_resettimer"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//计时器归零
      "colour_picker"				=>	Array("fields"=>Array(Array("COLOUR","text")),"inputs"=>Array(Array("COLOUR","text","TEXT"))),				//选取颜色

      //运算
      "operator_mod"				=>      Array("fields"=>Array(),"inputs"=>Array(Array("NUM1","math_number","NUM"),Array("NUM2","math_number","NUM"))),		//运算的，全在代码里实现。
      "operator_add"				=>      Array("fields"=>Array(),"inputs"=>Array(Array("NUM1","math_number","NUM"),Array("NUM2","math_number","NUM"))),		//运算的，全在代码里实现。
      "operator_subtract"			=>      Array("fields"=>Array(),"inputs"=>Array(Array("NUM1","math_number","NUM"),Array("NUM2","math_number","NUM"))),		//运算的，全在代码里实现。
      "operator_divide"				=>      Array("fields"=>Array(),"inputs"=>Array(Array("NUM1","math_number","NUM"),Array("NUM2","math_number","NUM"))),		//运算的，全在代码里实现。
      "operator_multiply"			=>      Array("fields"=>Array(),"inputs"=>Array(Array("NUM1","math_number","NUM"),Array("NUM2","math_number","NUM"))),		//运算的，全在代码里实现。
      "operator_random"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("FROM","math_number","NUM"),Array("TO","math_number","NUM"))),		//随机数
      "operator_contains"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("STRING1","text","TEXT"),Array("STRING2","text","TEXT"))),		//包含
      "operator_join"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("STRING1","text","TEXT"),Array("STRING2","text","TEXT"))),		//连接
      "operator_round"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("NUM","math_number","NUM"))),						//四舍五入
      "operator_length"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("STRING","text","TEXT"))),						//字符串长度
      "operator_mathop"				=>	Array("fields"=>Array(Array("OPERATOR")),"inputs"=>Array(Array("NUM","math_number","NUM"))),				//数学函数运算

      //变量
      "data_setvariableto"			=>	Array("fields"=>Array(Array("VARIABLE","")),"inputs"=>Array(Array("VALUE","text","TEXT"))),				//将变量设为
      "data_showvariable"			=>	Array("fields"=>Array(Array("VARIABLE","")),"inputs"=>Array()),								//显示变量
      "data_hidevariable"			=>	Array("fields"=>Array(Array("VARIABLE","")),"inputs"=>Array()),								//显示变量
      "data_changevariableby"			=>	Array("fields"=>Array(Array("VARIABLE","")),"inputs"=>Array(Array("VALUE","text","TEXT"))),				//修改列表某项数据
      "data_addtolist"				=>	Array("fields"=>Array(Array("LIST","list")),"inputs"=>Array(Array("ITEM","text","TEXT"))),				//添加到列表
      "data_itemoflist"				=>	Array("fields"=>Array(Array("LIST","list")),"inputs"=>Array(Array("INDEX","math_integer","NUM"))),			//列表的全部数据
      "data_deleteoflist"			=>	Array("fields"=>Array(Array("LIST","list")),"inputs"=>Array(Array("INDEX","math_integer","NUM"))),			//删除列表某一项数据
      "data_itemnumoflist"			=>	Array("fields"=>Array(Array("LIST","list")),"inputs"=>Array(Array("ITEM","text","TEXT"))),				//列表的某一项数据
      "data_listcontainsitem"			=>	Array("fields"=>Array(Array("LIST","list")),"inputs"=>Array(Array("ITEM","text","TEXT"))),				//列表是否包含某数据	//有额外的一个参数
      "data_deletealloflist"			=>	Array("fields"=>Array(Array("LIST","list")),"inputs"=>Array()),								//删除列表全部数据//除了列表名，无其他参数。
      "data_insertatlist"			=>	Array("fields"=>Array(Array("LIST","list")),"inputs"=>Array(Array("INDEX","math_integer","NUM"),Array("ITEM","text","TEXT"))),	//插入列表//有额外的两个参数
      "data_lengthoflist"			=>	Array("fields"=>Array(Array("LIST","list")),"inputs"=>Array()),								//列表长度
      "data_replaceitemoflist"			=>	Array("fields"=>Array(Array("LIST","list")),"inputs"=>Array(Array("INDEX","math_integer","NUM"),Array("ITEM","text","TEXT"))),	//替换列表某一项数据
      "data_showlist"				=>	Array("fields"=>Array(Array("LIST","list")),"inputs"=>Array()),								//显示列表
      "data_hidelist"				=>	Array("fields"=>Array(Array("LIST","list")),"inputs"=>Array()),								//隐藏列表

      //自制积木（无）

      //画笔
      "pen_clear"				=>	Array("fields"=>Array(),"inputs"=>Array()),										//全部擦除
      "pen_stamp"				=>	Array("fields"=>Array(),"inputs"=>Array()),										//图章
      "pen_penDown"				=>	Array("fields"=>Array(),"inputs"=>Array()),										//落笔
      "pen_penUp"				=>	Array("fields"=>Array(),"inputs"=>Array()),										//提笔
      "pen_setPenColorToColor"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("COLOR","colour_picker","COLOUR"))),					//设置画笔颜色
      "pen_changePenColorParamBy"		=>	Array("fields"=>Array(),"inputs"=>Array(Array("COLOR_PARAM","pen_menu_colorParam","colorParam"),
						 	                                        Array("VALUE","math_number","NUM"))),						//增加画笔参数
      "pen_setPenColorParamTo"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("COLOR_PARAM","pen_menu_colorParam","colorParam"),
						     	                                        Array("VALUE","math_number","NUM"))),						//设置画笔参数为
      "pen_setPenSizeTo"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("SIZE","math_number","NUM"))),            				//将笔的粗细设为
      "pen_changePenSizeBy"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("SIZE","math_number","NUM"))),						//将比的粗细增加
      "pen_menu_colorParam"			=>	Array("fields"=>Array(),"inputs"=>Array()),										//画笔参数菜单

       //音乐
      "music_playDrumForBeats"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("DRUM","text","TEXT"),Array("BEATS","math_number","NUM"))),  		//击打乐器n拍
      "music_restForBeats"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("BEATS","math_number","NUM"))),  						//休止n拍
      "music_playNoteForBeats"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("NOTE","note","NOTE"),Array("BEATS","math_number","NUM"))),  			//演奏音符n拍
      "music_setInstrument"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("INSTRUMENT","music_menu_INSTRUMENT","TEXT"))),  				//将乐器设为
      "music_setTempo"				=>	Array("fields"=>Array(),"inputs"=>Array(Array("TEMPO","math_number","NUM"))),  						//将演奏速度设定为
      "music_changeTempo"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("TEMPO","math_number","NUM"))),  						//将演奏速度增加
      "music_menu_DRUM"				=>	Array("fields"=>Array(Array("DRUM","text")),"inputs"=>Array(Array("DRUM","text","TEXT"))),  				//乐器列表
      "music_getTempo"				=>	Array("fields"=>Array(),"inputs"=>Array()),  						//将演奏速度设定为
      "note"					=>	Array("fields"=>Array(Array("NOTE","text")),"inputs"=>Array(Array("NOTE","math_number","NUM"))),  			//音符
      "music_menu_INSTRUMENT"			=>	Array("fields"=>Array(Array("INSTRUMENT","text")),"inputs"=>Array(Array("INSTRUMENT","text","TEXT"))),			//乐器菜单

      //互动工具
      "chattingroom_whenChatMessageComes"	=>	Array("fields"=>Array(),"inputs"=>Array()),										//当接收到聊天消息
      "chattingroom_sendMsgTo"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("USER","text","TEXT"),Array("MSG","text","TEXT"))),			//聊天室发送消息
      "chattingroom_lastReceivedMsg"		=>	Array("fields"=>Array(),"inputs"=>Array()),										//聊天室接收到的最近一条消息
      "chattingroom_lastReceivedMsgSender"	=>	Array("fields"=>Array(),"inputs"=>Array()),										//最后一条未读消息的发送者
      "chattingroom_lastMsgFrom"		=>	Array("fields"=>Array(),"inputs"=>Array(Array("USER","text","TEXT"))),							//来自某人的最后一条消息
      "chattingroom_sendReport"			=>	Array("fields"=>Array(),"inputs"=>Array(Array("STEPS","math_number","NUM"),Array("LEFT","math_number","NUM"),
						                                                Array("RIGHT","math_number","NUM"),Array("TIME","math_number","NUM"))),		//上报信息
      "chattingroom_splitString"		=>	Array("fields"=>Array(),"inputs"=>Array(Array("NEEDLE","text","TEXT"),Array("STRTEXT","text","TEXT"),
						                                                Array("LIST","text","TEXT"))),							//聊天室发送消息
      "chattingroom_menu_userlist"		=>	Array("fields"=>Array(Array("userlist","text")),"inputs"=>Array(Array("userlist","text","TEXT"))),			//聊天室用户列表
      "chattingroom_unreadMsgLength"		=>	Array("fields"=>Array(),"inputs"=>Array()),										//未读消息数
   );


   /*************************************************************************************************************************

      HATS白名单

        Hat类型的积木块。头部积木必须在这里注册，否则不会被识别为头部积木块。

        将opcode放在了key的位置，此时值无意义，
        这个设定，使得检测某个opcode是否为HATS操作，只要用isset就可以实现了，可以提升速度。

   *************************************************************************************************************************/
   private $isHATS=Array(
      "event_whenflagclicked"			=>0,			
      "event_whenkeypressed"			=>0,
      "event_whenthisspriteclicked"		=>0,
      "event_whenbackdropswitchesto"		=>0,
      "event_whengreaterthan"			=>0,
      "event_whenbroadcastreceived"		=>0,
      "control_start_as_clone"			=>0,
      "event_whenstageclicked"			=>0,
      "chattingroom_whenChatMessageComes"	=>0,
     // "control_wait_until"			=>0,//有包含的也不能删除next，除非后面真的没代码了。
     // "control_repeat_until"			=>0,//有包含的也不能删除next，除非后面真的没代码了。
     // "control_repeat"				=>0,//有包含的也不能删除next，除非后面真的没代码了。
   );

   /*************************************************************************************************************************

      构造函数初始化

      1.初始化用于解析表达式的RPN类
      2.将传入的序列化后的文本反序列化，还原成数组
      3.对数组中的文本进一步拆分：
          变量仅剩下：类型，变量名，等于号，值
          脚本只剩下：函数名，()，{}，参数
          且，脚本还按照代码段的概念，进行了拆分。

      构造函数初始化执行拆分操作后的数据格式：

        Array
        (
          [0] => Array				//0:适用于所有角色的变量
              (
                  [0] => VAR 我的变量 = 0
              )
          [1] => Array				//1:仅适用于当前角色的变量
              (
                  [0] => VAR i = 0
              )
          [2] => Array				//2:所有拆分成段的代码，不管带不带HATS，是不是自制积木，都在这里。
              (
                  [0] => Array
                      (
                          [0] => event_whenflagclicked
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
                          [14] => }
                      )
                  [2] => Array
                      (
                          [0] => motion_gotoxy
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
                          [11] => ;
                      )
                  [3] => Array
                      (
                          [0] => motion_gotoxy
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
                          [11] => ;
                      )
              )
        )

   *************************************************************************************************************************/
   function __construct( $strCODEDATA )
   {
      $this->rpn_calc     = new RPN_CALCULATION_EXPRESSION();		//处理四则混合运算的逆波兰类进行初始化
      $this->rpn_logic    = new RPN_LOGIC_EXPRESSION();			//处理逻辑运算的逆波兰类进行初始化
      $this->arrCODEDATA  = unserialize($strCODEDATA);			//处理由serialize处理数组后生成的字符串文本，还原成数组

      for($i=0;$i<4;$i++)			//拆分4个文本框传递过来的数据，其实第三和第四个文本框的数据合二为一了。
      {
         if($this->arrCODEDATA[$i]!=NULL)
         {
            if($i==0)							//现有的适用于所有角色的变量和对应的UID
            {
               $jsonNAMEID=json_decode($this->arrCODEDATA[0]);
               foreach($jsonNAMEID as $key=>$value)
               {
                  $this->arrVariableUIDS[$key]=$value;

               }
            }
            else if($i==1 || $i==2)					//拆分变量定义信息。
            {
               /****************************************************************************
                  $i：
                      0：适用于所有角色的变量   
                      1：仅适用于当前角色的变量

                      在这块定义的变量，只有VAR（变量）、LIST（列表）和MSG（消息）三种类型；
                      BOOL（布尔值）类型只有在自制积木（自定义函数）里有。

                      类C代码示例：
                          //适用于所有角色的变量
                          VAR 我的变量1=0;
                          LIST 我的列表1={};

                          //适用于当前角色的变量
                          VAR  我的变量2 = "abc";		//变量初始化
                          LIST 我的列表2 ={1,2,3};		//列表初始化

                      解析后结果：
                          [0] => Array				//$i=0：适用于所有角色的变量，实际定义于舞台角色内。
                              (
                                 [0] => VAR 我的变量1=0
                                 [1] => LIST 我的列表1={}
                              )
                          [1] => Array				//$i=1：仅适用于当前角色的变量，实际定义于当前角色内。
                             (
                                [0] => VAR 我的变量2 = "abc"
                                [1] => LIST 我的列表2 ={1,2,3}
                             )

                  注意：
                      变量的定义，严格区分适用范围。
                      Scratch3中允许两种类型的变量名可以重名，因为相关的变量在显示时会注明属于哪个角色；
                      而文本代码里，没有有效的办法来区分，所以不允许两者有同名变量，
                      如果出现同名变量，适用于当前角色的变量的定义会被舍去。

               ****************************************************************************/

               $strPattern=Array('/\/\*([^^]*?)\*\//','/\/\/([^^]*?)\n/');
               $strReplacement=Array('','');
               $this->arrCODEDATA[$i]=preg_replace($strPattern,$strReplacement,$this->arrCODEDATA[$i]);		//删除注释和换行
               $this->arrCODEDATA[$i]=array_filter(explode(";",$this->arrCODEDATA[$i]));			//按“;”拆分
            }
            else //$i==3			//拆分脚本数据
            {
               /****************************************************************************
                  $i：
                      2： 带HATS的积木、自制积木，其他积木，都在这里。

                      HATS积木，即为如下积木：
                          当绿旗被点击，当按下空格键，当角色被点击，当背景换成背景1，当响度>10，当接收到消息1，当座位克隆体启动时和自制积木。

                      类C代码：
                         //例一：
                         motion_gotoxy(0,0);					//只有一个回车换行符的代码视作同一代码段
                         motion_pointindirection(90);
										//此处的超过一个的连续回车换行符，表示另起一个代码段
                         motion_gotoxy(10,10);
                         motion_pointindirection(180);

                         //例二：
                         event_whenflagclicked()				//代码通过{}匹配来拆分
                         {
                            motion_movesteps(10);
										//在带HATS或{}内的代码段，不会因为有连续的回车换行符而另起一段代码。
                            motion_turn right(15);
                         }							//此为带HATS的代码段，此处结束后，后续如果还有代码，将另起一段。

                         //例三：
                         void func_a__b_info(VAR a, VAR b)
                         {
                            motion_gotoxy(a,b);
                         }

                         //例四：
                         if((1>2&&2<3)){}					//if(){}也按照{}来处理，但if...else...需要特别对待。


                         else{}							//不管中间有多少空行，都必须视作同一段代码

                      解析后结果：
                          [2] => Array					//$i=2：为代码段
                              (
                                 [0] => Array				//例一
                                     (
                                         [0] => motion_gotoxy
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
                                         [11] => ;
                                     )
                                 [1] => Array					//因为有连续的空行，所以例一代码将被拆分成两段。
                                     (
                                         [0] => motion_gotoxy
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
                                         [11] => ;
                                     )
                                 [4] => Array				//例二
                                     (
                                         [0] => event_whenflagclicked		//以HAT类型开始的积木块段，只以“{}”的配对情况进行分段。
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
                                         [14] => }
                                     )
                                 [5] =>					//例三
                                     (
                                         [0]=> SELFDEFINED_FUNCTION"
                                         [1]=> void func_a__b_info
                                         [2]=> (
                                         [3]=> VAR a
                                         [4]=> ,
                                         [5]=> VAR b
                                         [6]=> )
                                         [7]=> {
                                         [8]=> motion_gotoxy
                                         [9]=> (
                                         [10]=> a
                                         [11]=> ,
                                         [12]=> b
                                         [13]=> )
                                         [14]=> ;
                                         [15]=> }
                                     )
                                 [7] => Array()				//例四  //代码段的偏移量不需要连续，只要能准确地分割代码段即可。
                                     (
                                         [0]=> if
                                         [1]=> (
                                         [2]=> (1>2&&2<3)				//所有条件数据都在一起
                                         [3]=> )
                                         [4]=> {
                                         [5]=> }
                                         [6]=> else					//过滤掉了多余的空行，整体视作一个代码段
                                         [7]=> {
                                         [8]=> }
                                     )
                              )

               ****************************************************************************/

               $arrCodeSection		= Array(); 			//带HATS的代码段，与用多回车分隔的代码段，统一解析在一个数组里。   
               $nSECTION		= 0;				//代码段号
               $nRNCounter		= 0;				//空行回车计数器

               /****************************************************************************

                  文本代码拆分算法：

                     1.删除注释
                       ------------------------------------------
                         替换前   |  \/*注释*\/  |   //注释\n   |
                       -----------|--------------|--------------|
                         替换后   |              |              |
                       ------------------------------------------

                     2.将连续的两个换行符替换成三个
                       ----------------------
                         替换前   |  \n\n   |
                       -----------|---------|
                         替换后   | \n\n\n  |  使用了ACE编辑器后，需要追加一条规则：当原本有两个连续的换行时，将此组换行替换成三个换行符号。
                       ----------------------

                     3.添加换行符
                       ------------------------------------------------------------
                         替换前   |   {   |   }   |   (   |   )   |   ;   |   ,   |
                       -----------|-------|-------|-------|-------|-------|-------|
                         替换后   | \n{\n | \n}\n | \n(\n | \n)\n | \n;\n | \n,\n |
                       ------------------------------------------------------------

                     4.拆分
                       直接按照“\n”进行拆分。


               ****************************************************************************/
               $strPattern		=Array( '/\/\*([^^]*?)\*\//',  '/^\/\/.*$/m');
               $strReplacement		=Array( '',                    '',               );
               $this->arrCODEDATA[3]	=preg_replace($strPattern,$strReplacement,$this->arrCODEDATA[3]);			//1.删除注释

               $strPattern		=Array( '/\\n\\n/');
               $strReplacement		=Array( "\n\n\n"   );
               $this->arrCODEDATA[3]	=preg_replace($strPattern,$strReplacement,$this->arrCODEDATA[3]);			//2.将连续的两个换行换成三个换行

               $strPattern		=Array('/\\}/',   '/\\{/',   '/;/',    '/\\(/',   '/\\)/',   '/,/',    '/\!/'  );
               $strReplacement		=Array("\n}\n",   "\n{\n" ,  "\n;\n",  "\n(\n",   "\n)\n",   "\n,\n",  "\n!\n" );
               $arrRawSplitCodeData	= explode("\n",preg_replace($strPattern,$strReplacement,$this->arrCODEDATA[3]));	//3.添加换行符 4.拆分
               $nRawSplitCodeDataLength	= count($arrRawSplitCodeData);

               /****************************************************************************

                  代码段拆分规则：

                      1. HATS积木和自制积木（自定义函数“func(){}”），当最后一个“}”出现后，需要分段：$nSECTION++;
                      2. “for()”、“do{}while();”和“if(){}else{}”这些结构体，内部不管有多少空行，都不分段；
                      3. 其他代码，若连续出现多个空行（$nRNCounter>1），就分段。

               ****************************************************************************/

               //遍历处理所有数据，按规则拆分成段
               for($n=0;$n<$nRawSplitCodeDataLength;$n++)			
               {
                  $strCode=trim($arrRawSplitCodeData[$n]);				//过滤无用的空格

                  if(isset($this->isHATS[$strCode]))		/******** HATS代码的处理 ********/
                  {
                     /****************************************************************************

                        //例一：当绿旗被点击
                        event_whenflagclicked(){					//步骤a：处理参数，直到第一个“{”出现
                           //其他可能存在的代码						//步骤b：采集被包含的代码
                        }								//步骤c：检测最后一个“}”出现

                        //例二：当空格被按下
                        event_whenkeypressed("space"){
                           //其他可能存在的代码
                        }

                        HATS积木，基本是固定的，所以可以使用白名单isHATS来识别。

                     ****************************************************************************/

                     $nSECTION++;							//发现HATS积木，新代码段开始。
                     $nBraceCounter=0;							//{}计数器
                     $arrCodeSection[$nSECTION][] = $strCode;				//保存HATS积木块名称

                     while($n<$nRawSplitCodeDataLength-1)				//步骤a
                     {
                        $strCode=trim($arrRawSplitCodeData[++$n]);

                        if($strCode!="")  $arrCodeSection[$nSECTION][] = $strCode;	//可能存在的参数，如例二中的“space”.
                        if($strCode=="{") {$nBraceCounter=1;break;}			//找到{，终止循环。为防止遍历完依旧没找到，所以要设置一个控制量$nBraceCounter.
                     }
                     $n++;								//增1，避免数据重复。
                     if($nBraceCounter!=1) return;					//如果while结束了仍没找到{，则表示数据异常。

                     while( $n<$nRawSplitCodeDataLength-1)				//步骤b
                     {
                        $strCode=trim($arrRawSplitCodeData[$n++]);

                        if($strCode!="")  $arrCodeSection[$nSECTION][] = $strCode;	//可能存在的函数调用参数
                        if($strCode=="{")      $nBraceCounter++;			//找到{，$nBraceCounter计数器自增
                        else if($strCode=="}") $nBraceCounter--;			//找到}，$nBraceCounter计数器自减
                        if($nBraceCounter==0) break; 					//步骤c，找到最后一个}，终止循环
                     }

                     if($nBraceCounter!=0) return;					//如果while结束却没有让{}得到匹配，就表示数据异常。
                     $nSECTION++;							//HATS积木处理结束，下一个代码段开始。
                  }
                  else  if(strpos($strCode,"void ")!==FALSE)	/******** 自制积木代码的处理 ********/
                  {
                     /****************************************************************************

                        //例一：自定义无参数的函数func()
                        void func(){							//步骤a：处理参数，直到第一个“{”出现
                           //其他可能存在的代码						//步骤b：采集被包含的代码
                        }								//步骤c：检测最后一个“}”出现

                        //例二：自定义带两参数的函数func_a__b_info()
                        void func_a__b_info(VAR a, BOOL b){
                           //其他可能存在的代码
                        }

                        对自制积木（自定义函数）的处理，大致与HATS类的相同。
                        HATS类积木是固定的，有白名单可以识别，而自制积木的名字是随意的，无法靠白名单来识别，因此需要加上特殊数据头。

                     ****************************************************************************/

                     $arrSDFunctions = Array();						//这里保存了所有的自制积木的定义块，对这里的定义进行优先处理。

                     $nSECTION++;							//发现自制积木，新代码段开始。

                     $arrCodeSection[$nSECTION][] = "SELFDEFINED_FUNCTION";		//自制积木的特殊数据头
                     $arrCodeSection[$nSECTION][] = $strCode;				//自制积木的函数名称

                     $arrSDFunctions[]=$strCode;					//自制积木的定义需要优先于所有代码，否则不能正确识别自制积木

                     $nBraceCounter=0;							//{}计数器归零
                     while($n<$nRawSplitCodeDataLength-1)				//步骤a
                     {
                        $strCode=trim($arrRawSplitCodeData[++$n]);

                        if($strCode=="{") {$nBraceCounter=1;break;}

                        if($strCode!="")  {
                           //$arrCodeSection[$nSECTION][] = $strCode;			//由于程序会在解析所有代码前先解析自定义函数的参数，所以在解析所有代码时，就不需要这些数据了。
                           $arrSDFunctions[]=$strCode;					//采集自制积木的参数信息，然后用defineSelfDefinedFunction()对自定义函数进行解析。
                        }
                     }

                     /****************************************************************************

                        $arrSDFunctions里只有函数的定义信息，而无函数的实现代码。
                        通过在解析积木前将自定义函数先行解析，
                        可以使自定义函数在被调用时能被准确识别。

                        Array
                        (
                            [0] => void func_a__b_
                            [1] => (
                            [2] => VAR a
                            [3] => ,
                            [4] => BOOL b
                            [5] => )
                        )

                     ****************************************************************************/

                     $this->defineSelfDefinedFunction($arrSDFunctions);			//解析自制自定义函数，获取自制积木的函数名、proccode、参数名和参数类型。

                     $arrCodeSection[$nSECTION][] = '{';
                     $n++;
                     if($nBraceCounter!=1) return;					//异常处理，暂时无意义。

                     while( $n<$nRawSplitCodeDataLength-1)				//步骤b
                     {
                        $strCode=trim($arrRawSplitCodeData[$n++]);

                        if($strCode!=""){
                           $arrCodeSection[$nSECTION][] = $strCode;
                        }
                        if($strCode=="{")      $nBraceCounter++;
                        else if($strCode=="}") $nBraceCounter--;
                        if($nBraceCounter==0) break; 					//步骤c
                     }

                     //echo "[$n]:".$arrRawSplitCodeData[$n]."\n";
                     $n--;								//退1
                     /******************************************************************************************************
                        $arrCodeSection里没有参数的信息，
                        因为前面的defineSelfDefinedFunction()已经完成了函数定义操作。

                        Array
                        (
                            [1] => Array
                                (
                                    [0] => SELFDEFINED_FUNCTION
                                    [1] => void func_a__b_
                                    [2] => {
                                    [3] => 我的变量=
                                    [4] => 10
                                    [5] => ;
                                    [6] => }
                                )
                        )

                     ******************************************************************************************************/

                     if($nBraceCounter!=0) return;					//异常处理，暂时无意义。
                     $nBraceCounter=0;
                     $nSECTION++;							//自制积木处理结束，下一个代码段开始。
                  }
                  else if($strCode=="for")			/******** for代码段 ********/
                  {
                     /******************************************************************************************************

                        //例一：当绿旗被点击
                        for(int i=0;i<10;i++){						//步骤a：处理参数，直到第一个“{”出现
                           //其他可能存在的代码						//步骤b：采集被包含的代码
                        }								//步骤c：检测最后一个“}”出现

                     *******************************************************************************************************/

                     $arrCodeSection[$nSECTION][] = $strCode;				//填入for
                     $arrCodeSection[$nSECTION][] = '(';				//步骤a  填入(，开始解析条件(int i=0;i<(10+3);$i++)

                     $n++;
                     $nParenthesesCounter=1;						//跳过(，置小括号计数器为1
                     $strFORcondition='';
                     while($n<$nRawSplitCodeDataLength-1)				//一直扫描到数组末尾
                     {
                         $strCode=trim($arrRawSplitCodeData[++$n]);			//取数据
                         if($strCode=='(') $nParenthesesCounter++;			//如果遇到(，计数器自增
                         else if($strCode==')') $nParenthesesCounter--;			//如果遇到)，计数器自减
                         if($nParenthesesCounter==0) break;				//如果计数器归零，跳出while

                         if($strCode)
                            $strFORcondition .= $strCode;
                     }
                     $arrCodeSection[$nSECTION][] = $strFORcondition;			//非空，就保存，过滤掉空行。

                     $arrCodeSection[$nSECTION][] = ')';				//补)

                     while($n<$nRawSplitCodeDataLength-1)				//检测{开始位
                     {
                         $strCode=trim($arrRawSplitCodeData[++$n]);
                         if($strCode=="") continue;					//空行，继续
                         else if($strCode=='{') break;					//{，跳出while
                         else break;							//其他字符，跳出while
                     }

                     $arrCodeSection[$nSECTION][] = '{';				//步骤b  补{

                     $nBraceCounter=1;							//大括号计数器自增
                     while($n<$nRawSplitCodeDataLength-1)
                     {
                         $strCode=trim($arrRawSplitCodeData[++$n]);
                         if($strCode=='{') $nBraceCounter++;
                         else if($strCode=='}') $nBraceCounter--;
                         if($nBraceCounter==0) break;					//当大括号计数器归零，则跳出while
                         if($strCode)
                            $arrCodeSection[$nSECTION][] = $strCode;
                     }
                     $arrCodeSection[$nSECTION][] = '}';				//步骤c   补}
                     $nRNCounter=0;                 
                  }
                  else if($strCode=="if")			/******** if代码段 ********/
                  {
                     /******************************************************************************************************

                        //例一：当绿旗被点击
                        if(  条件  ){							//步骤a：处理参数，直到第一个“{”出现
                           //其他可能存在的代码						//步骤b：采集被包含的代码
                        }								//步骤c：检测最后一个“}”出现

                        //例二：当空格被按下
                        if(  条件  ){
                           //其他可能存在的代码
                        }
                        else								//步骤d：检测是否有else
                        {								//步骤e
                           //其他可能存在的代码						//步骤f
                        }								//步骤g

                     *******************************************************************************************************/

                     $arrCodeSection[$nSECTION][] = $strCode;				//填入if

                     $arrCodeSection[$nSECTION][] = '(';				//步骤a  填入(，开始解析条件()
                     $n++;
                     $nParenthesesCounter=1;						//跳过(，置小括号计数器为1
                     $strIFcondition='';
                     while($n<$nRawSplitCodeDataLength-1)				//一直扫描到数组末尾
                     {
                         $strCode=trim($arrRawSplitCodeData[++$n]);			//取数据
                         if($strCode=='(') $nParenthesesCounter++;			//如果遇到(，计数器自增
                         else if($strCode==')') $nParenthesesCounter--;			//如果遇到)，计数器自减
                         if($nParenthesesCounter==0) break;				//如果计数器归零，跳出while

                         if($strCode)
                            $strIFcondition .= $strCode;				//非空，就保存，过滤掉空行。
                     }
                     $arrCodeSection[$nSECTION][] = $strIFcondition;			//补条件
                     $arrCodeSection[$nSECTION][] = ')';				//补)

                     while($n<$nRawSplitCodeDataLength-1)				//检测{开始位
                     {
                         $strCode=trim($arrRawSplitCodeData[++$n]);
                         if($strCode=="") continue;					//空行，继续
                         else if($strCode=='{') break;					//{，跳出while
                         else break;							//其他字符，跳出while
                     }

                     $arrCodeSection[$nSECTION][] = '{';				//补{

                     $nBraceCounter=1;							//大括号计数器自增
                     while($n<$nRawSplitCodeDataLength-1)				//步骤b
                     {
                         $strCode=trim($arrRawSplitCodeData[++$n]);
                         if($strCode=='{') $nBraceCounter++;
                         else if($strCode=='}') $nBraceCounter--;
                         if($nBraceCounter==0) break;					//当大括号计数器归零，则跳出while
                         if($strCode)
                            $arrCodeSection[$nSECTION][] = $strCode;			//获取被包含的代码
                     }
                     $arrCodeSection[$nSECTION][] = '}';				//补}

                     $n2=$n;								//步骤d   检测else需要消耗偏移量，所以用另一个变量来继续检索

                     $bELSE=false;							//检测是否有else
                     while($n2<$nRawSplitCodeDataLength-1)				//检测{开始位
                     {
                         $strCode=trim($arrRawSplitCodeData[++$n2]);
                         if($strCode=="") continue;					//空行，继续
                         else if($strCode=='else'){$bELSE=true; break;}			//else，跳出while
                         else break;							//其他字符，跳出while
                     }

                     if($bELSE)
                     {
                        $n=$n2;								//如果找到，则偏移量调整，否则还是原来的偏移量
                        $arrCodeSection[$nSECTION][] = 'else';				//补else

                        while($n<$nRawSplitCodeDataLength-1)				//步骤e   检测{开始位
                        {
                            $strCode=trim($arrRawSplitCodeData[++$n]);
                            if($strCode=="") continue;					//空行，继续
                            else if($strCode=='{') break;				//{，跳出while
                            else break;							//其他字符，跳出while
                        }

                        $arrCodeSection[$nSECTION][] = '{';				//补{
                        //$n++;
                        $nBraceCounter=1;						//步骤f  大括号计数器自增
                        while($n<$nRawSplitCodeDataLength-1)
                        {
                            $strCode=trim($arrRawSplitCodeData[++$n]);
                            if($strCode=='{') $nBraceCounter++;
                            else if($strCode=='}') $nBraceCounter--;
                            if($nBraceCounter==0) break;				//当大括号计数器归零，则跳出while
                            if($strCode)
                               $arrCodeSection[$nSECTION][] = $strCode;
                        }
                        $arrCodeSection[$nSECTION][] = '}';				//步骤g  补}
                     }

                     $nRNCounter=0;
                  }
                  else if($strCode=="do")			/******** 重复执行，重复执行直到<条件>，等待<条件> ********/
                  {
                    /*---------------------------------------------------------------------------
                     |  功 能  |     重复执行      |   重复执行直到<条件>   |   等待<条件>      |
                     |---------|-------------------|------------------------|-------------------|
                     |         |   do{             |   do{                  |   do{             |
                     |   代    |      //分支代码   |      //代码分支        |      //无代码     |
                     |   码    |   }               |   }                    |   }               |
                     |         |   while(1);       |   while(!条件);        |   while(!条件);   |
                     |---------|-------------------|------------------------|-------------------|
                     |  区 分  |        ↑只有1    |   有条件，有分支代码   |   有条件，无代码  |
                     ---------------------------------------------------------------------------*/

                     $arrCodeSection[$nSECTION][] = $strCode;				//填入do

                     while($n<$nRawSplitCodeDataLength-1)				//检测{开始位
                     {
                        $strCode=trim($arrRawSplitCodeData[++$n]);
                        if($strCode=="") continue;					//空行，继续
                        else if($strCode=='{') break;					//{，跳出while
                        else break;							//其他字符，跳出while
                     }

                     $arrCodeSection[$nSECTION][] = '{';				//填入{，开始解析条件(int i=0;i<(10+3);$i++)
                     $n++;
                     $nBraceCounter=1;							//跳过(，置小括号计数器为1

                     $nCheckChildFunc=$n;
                     while($n<$nRawSplitCodeDataLength-1)				//一直扫描到数组末尾
                     {
                        $strCode=trim($arrRawSplitCodeData[++$n]);			//取数据
                        if($strCode=='{') $nBraceCounter++;				//如果遇到{，计数器自增
                        else if($strCode=='}') $nBraceCounter--;			//如果遇到}，计数器自减
                        if($nBraceCounter==0) break;					//如果计数器归零，跳出while

                        if($strCode)
                           $arrCodeSection[$nSECTION][] = $strCode;			//非空，就保存，过滤掉空行。
                     }

                     if($n-$nCheckChildFunc>1)
                        $arrCodeSection[$nSECTION][] = '';				//补}，代码分支获取完毕

                     $arrCodeSection[$nSECTION][] = '}';				//补}，代码分支获取完毕

                     while($n<$nRawSplitCodeDataLength-1)				//检测while开始位
                     {
                        $strCode=trim($arrRawSplitCodeData[++$n]);
                        if($strCode=="") continue;					//空行，继续
                        else if($strCode=='while') break;				//while，跳出while
                        else break;							//其他字符，跳出while
                     }

                     $arrCodeSection[$nSECTION][] = 'while';				//填入while

                     while($n<$nRawSplitCodeDataLength-1)				//检测(开始位
                     {
                        $strCode=trim($arrRawSplitCodeData[++$n]);
                        if($strCode=="") continue;					//空行，继续
                        else if($strCode=='(') break;					//(，跳出while
                        else break;							//其他字符，跳出while
                     }

                     $arrCodeSection[$nSECTION][] = '(';				//补(

                     $nParenthesesCounter=1;						//大括号计数器自增
                     $strWHILEcondition="";
                     while($n<$nRawSplitCodeDataLength-1)				//获取while的条件
                     {
                        $strCode=trim($arrRawSplitCodeData[++$n]);
                        if($strCode=='(') $nParenthesesCounter++;
                        else if($strCode==')') $nParenthesesCounter--;
                        if($nParenthesesCounter==0) break;				//当大括号计数器归零，则跳出while
                        if($strCode)
                           $strWHILEcondition.= $strCode;				//把条件放在一个字符串里
                     }

                     $arrCodeSection[$nSECTION][] = $strWHILEcondition;
                     $arrCodeSection[$nSECTION][] = ')';				//补)

                     while($n<$nRawSplitCodeDataLength-1)				//检测;开始位
                     {
                        $strCode=trim($arrRawSplitCodeData[++$n]);
                        if($strCode=="") continue;					//空行，继续
                        else if($strCode==';') break;					//;，跳出while
                        else break;							//其他字符，跳出while
                     }
                     $arrCodeSection[$nSECTION][] = ';';				//补;

                     $nRNCounter=0;
                  }
                  else						/******** 其他代码，都没有{}，所以只需要根据连续换行符来确认是否要增段拆开。 ********/
                  {

//echo "for ELSE $n\n";
                     if($strCode!="")							//非空行
                     {
                        $arrCodeSection[$nSECTION][] = $strCode;			//装配数据
                        $nRNCounter=0;							//空行计数器归零。
                     }
                     else 								//出现空行
                     {
                        $nRNCounter++;							//空行计数器增1

                        if( $nRNCounter>1 )						//连续出现多个空行，则代码段编号自增
                           $nSECTION++;							//后续利用这些数据的算法，用了foreach来遍历数据，所以这个段号不需要保持连续。
                     }
                  }
               }
               $this->arrCODEDATA[3]=$arrCodeSection;		//将整段数据装配
            }//else $i==2
         }//if($this->arrCODEDATA[$i]!=NULL)
      }//for()

      if(1|DEBUGs) var_dump($this->arrCODEDATA);			//调试用。
   }


   /************************************************************************************************************************
   *
   *   数据处理主入口
   *
   *   负责对两种变量的解析，并调用处理程序解析已分段的类C语言代码。
   *
   *   $arrCODEDATA = Array
   *                (
   *                   [0] => Array				//适用于所有角色的变量
   *                       (
   *                             [0] => VAR 我的变量 = 0
   *                             [1] => VAR 我的变量2 = 0
   *                       )
   *                   [1] => Array(				//仅适用于当前角色的变量
   *                       (
   *                             [0] => VAR a = 0
   *                             [1] => VAR b = 0
   *                       )
   *                   [2] => Array				//类C语言代码
   *                       (
   *                             [1] => Array
   *                                 (
   *                                       [0] => SELFDEFINED_FUNCTION
   *                                       [1] => void func_a__b_
   *                                       [2] => {
   *                                       [3] => }
   *                                 )
   *                             [2] => Array
   *                                 (
   *                                       [0] => if
   *                                       [1] => (
   *                                       [2] => (1 > 2)
   *                                       [3] => )
   *                                       [4] => {
   *                                       [5] => if
   *                                       [6] => (
   *                                       [7] => (
   *                                       [8] => 2 > 3
   *                                       [9] => )
   *                                       [10] => )
   *                                       [11] => {
   *                                       [12] => if
   *                                       [13] => (
   *                                       [14] => (
   *                                       [15] => 4 > 3
   *                                       [16] => )
   *                                       [17] => )
   *                                       [18] => {
   *                                       [19] => for
   *                                       [20] => (
   *                                       [21] => int i1 = 0
   *                                       [22] => ;
   *                                       [23] => i1 < 10
   *                                       [24] => ;
   *                                       [25] => i1++
   *                                       [26] => )
   *                                       [27] => {
   *                                       [28] => for
   *                                       [29] => (
   *                                       [30] => int i2 = 0
   *                                       [31] => ;
   *                                       [32] => i2 < 10
   *                                       [33] => ;
   *                                       [34] => i2++
   *                                       [35] => )
   *                                       [36] => {
   *                                       [37] => }
   *                                       [38] => }
   *                                       [39] => }
   *                                       [40] => }
   *                                       [41] => }
   *                                )
   *                       )
   *                )
   *
   *************************************************************************************************************************/
   function deal()
   {
      $arrScratch3Data = Array(Array(),Array(),Array());//存放解析后的数据（适用于所有角色的变量，仅适用于当前角色的变量，积木的JSON数据）

      foreach($this->arrCODEDATA as $index=>$arrData)	//遍历arrCODEDATA（共3组数据）
      {
         switch($index)
         {
         //case 0:					//已有适用于所有角色的变量的UID已经在前面处理了，此处留空。
         //   break;
         case 1:
         case 2:					//仅适用于当前角色的变量
            if($arrData=="") break;								//数据为空，不处理
            $arrVariables=Array();
            for($i=0;$i<count($arrData);$i++)
            {
               if(preg_match("/([^^]*?) ([^^]*?)=([^^]*?);/",$arrData[$i].";",$matchedVariables))	//变量按定义格式拆分			
               {
                  $strVariableName=trim($matchedVariables[2]);

                  
                  $thisVariableUID=isset($this->arrVariableUIDS[$strVariableName])?$this->arrVariableUIDS[$strVariableName]:UID();	//检查是否是已定义过的变量

                  /***************       UID    *****    类型         ******        名字      ******  默认值        ***********/
                  $arrVariables[]=Array($thisVariableUID,trim($matchedVariables[1]),$strVariableName ,trim($matchedVariables[3]));

                  /***************       变量名       *******        UID    *********/
                  $this->arrVariableUIDS[$strVariableName]=$thisVariableUID;		//给每个变量预设一个UID。对于适用于所有角色的变量，后期要从当前项目获取现有UID，填充到这里，防止因UID改变而使得其他角色代码出错。
               }
            }
            $arrScratch3Data[$index-1]=$arrVariables;						//两种变量分别放在[0]和[1]处。

            break;

         case 3:					//类C语言文本代码
            if($arrData=="") break;								//数据为空，不处理

            foreach($arrData as $key=>$arrSection)						//$key不连续
            {
               /****************************************************************************

                  每一个代码段的第一个积木块，都有
                      唯一的id；
                      parent值都为null；							//本程序中有‘null’和‘NULL’，PHP程序逻辑判断中用‘NULL’，生成的积木JSON数据中用‘null’。
                      next指向下一个积木（不是分支），若无，也为null；				//在积木JSON数据生成时，有一个将NULL转为'null'的操作。
                      toplevel为true。

                  其余积木块，
                      parent指向上一块积木的id；
                      next指向下一个积木（不是分支），若无，也为null；
                      toplevel为false								//如果也为true，则会显示两个同一ID的积木。

                  要想让上一块积木能够知道下一块积木的id，
                  比较简单的办法，就是在生成上一块积木的完整数据前，先生成下一块积木的id。

                  所以，第一块积木的id，在调用parseSpecialBlocks()前，就已经创建好了。
                  而又因为第一块积木的parent是null，所以$this->UIDS的第一个数据为NULL。
                  
               ****************************************************************************/
               $this->UIDS=Array(NULL,UID());							//每个循环，都是一个独立的代码段，所以每次都需要初始化一下：parent_uid,this_uid
               $this->bTOPLEVEL="true";								//只有代码段顶部积木，才能设toplevel为true，否则显示会混乱（出现重复的积木）；另，如果全为false，则会不显示。

               $this->parseSpecialBlocks($arrSection);						//处理被拆分的代码文本数据，处理完的数据直接放在Blockly[0]里

               /****************************************************************************

                  每一个代码段的最后一块积木，其next值，应该为null，
                  否则在拖动生成的积木时，部分代码段间会出现粘连的现象。

                  在类C语言文本代码中，虽然HATS类函数的{}内的代码跟if/else的{}内的代码逻辑是相同的，
                  但在Scratch3.0的实现中，是不同的。

                  if/else的{}内的数据，是由SUBSTACK来指向的；
                  HATS类的{}内的数据，是由next类指向的。

                  除非HATS类积木后面不带任何积木，否则它的next不能为null。

                  而对于后面不带任何积木的HATS积木，在JSON数据生成时，已做了相应的处理。

               ****************************************************************************/
               $lastBlock=array_pop($this->Blockly);
               if($lastBlock){
                  $jsonBlock=json_decode($lastBlock);

                  if(!isset($this->isHATS[$jsonBlock->{'opcode'}]))			//非HATS类，强制修改next数据。
                  {
                     $jsonBlock->{'next'}=NULL;
                     array_push($this->Blockly,json_encode($jsonBlock));		//jsonDecode和jsonEncode后的数据，会删掉多余的空格。
                  }
                  else
                     array_push($this->Blockly,$lastBlock);				//即使解析出错，也原样保存，便于后续排错。
               }
            }
           
            $arrScratch3Data[2]=$this->Blockly;							//用[2]而不是[$index]，会快点？嗯，差异可以忽略不计。

            break;
         }
      }

      if(1||DEBUGs){ print_r($this->Blockly);print_r($arrScratch3Data);}				//输出所有积木信息

      return $arrScratch3Data;
   }


   /*******************  关于积木块的JSON数据    **************************************************************************************************************
   //实际类型有不带参数的，带默认值参数的，带变量参数的，以及inputs和fields都有数据的。

   //示例一（参数非变量）：
   {							//积木主体
      "id": "dO9Pm)z^~;pMKM$NRI@B",					//积木块ID
      "opcode": "motion_movesteps",					//积木块的opcode
      "inputs": {							//输入参数
         "STEPS": {
            "name": "STEPS",						//参数名
            "block": "v);_)]M(P#H[OxSM5y-*",				//参数实际对应的积木块ID
            "shadow": "v);_)]M(P#H[OxSM5y-*"				//参数默认值所对应的积木块ID。
         }								//    当参数是普通文本或者变量时，block和shadow的值一致；
      },								//    不一致时，表示实际值为变量或公式，此时，移除变量或公式积木块，就会显示shadow指向的值。
      "fields": {},							//字段
      "next": null,							//下一块积木的ID
      "topLevel": true,							//积木段的第一块积木，必须是true，否则不会显示
      "parent": null,							//上一块积木的ID，由于此积木是第一块，所以parent数据为null
      "shadow": false,							//不使用shadow指向的参数积木的值
      "x": 54.96296296296293,						//坐标如果无数据，就是（0,0）
      "y": 163.25925925925924
   },
   {							//积木参数，可以是固定值，也可以是变量
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
        "inputs": {							//输入
            "ITEM": {
                "name": "ITEM",
                "block": "%Wc)olM@_$`OyaiEJ8Ki",
                "shadow": "%Wc)olM@_$`OyaiEJ8Ki"
            }
        },
        "fields": {							//字段
            "LIST": {
                "name": "LIST",
                "id": ":}t:07bHbF|aihdeu!o4",
                "value": "我的列表",					//列表名
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
                "value": "东西"						//文本数据
            }
        },
        "next": null,
        "topLevel": false,
        "parent": "jZpHB^?I*NVW1XCy2?8H",
        "shadow": true
     }
   }

   ******************************************************************************************************************************************************************/

   /********************  关于积木块的UID (Unique ID)  ***************************************************************************************************************

      UIDS=Array(0->NULL,1->UID());	//默认值（thisUID0,nextUID0）

      [0]      pop(1:nextUID0->thisUID)  pop(0:thisUID0->parentUID) push(thisUID[nextUID0]->0) push(NEW nextUID1 ->1)//这个nextUID1应该给[1]用

               //开始递归前
               push(thisUID[nextUID0]->2)  push(NEW subTrack[nextUID2]->3)

         [00]      pop(3:nextUID2->thisUID)  pop(2:nextUID0->parentUID) push(thisUID[nextUID2]->2) push(NEW nextUID3->3)
         [01]      pop(3:nextUID3->thisUID)  pop(2:nextUID2->parentUID) push(thisUID[nextUID3]->2) push(NEW nextUID4->3)
         [02]      pop(3:nextUID4->thisUID)  pop(2:nextUID3->parentUID) push(thisUID[nextUID4]->2) push(NEW nextUID5->3)//这个nextUID5给[03]用

                   //开始递归前，加压入thisUID和nextUID
                   push(thisUID[nextUID4]->4) push(NEW subTrack[nextUID6]->5)  	//要进入递归程序，需要多压一个thisUID。

            [000]      pop(5:nextUID6->thisUID)  pop(4:nextUID4->parentUID) push(thisUID[nextUID6]->4) push(NEW nextUID7->5)
            [001]      pop(5:nextUID7->thisUID)  pop(4:nextUID6->parentUID) push(thisUID[nextUID6]->4) push(NEW nextUID8->5)
            [002]      pop(5:nextUID8->thisUID)  pop(4:nextUID6->parentUID) push(thisUID[nextUID8]->4) push(NEW nextUID9->5)

                   //结束递归后
                   pop(5:nextUID9舍去)   pop(4:nextUID8舍去)

         [03]      pop(3:nextUID5->thisUID)  pop(2:nextUID4->parentUID) push(thisUID[nextUID5]->2) push(NEW nextUID10->3)
         [04]      pop(3:nextUID10->thisUID)  pop(2:nextUID5->parentUID) push(thisUID[nextUID10]->2) push(NEW nextUID11->3)

               //结束递归后
               pop(3:nextUID11舍去)   pop(2:nextUID10舍去)  

      [1]      pop(1:nextUID1->thisUID)  pop(0:nextUID0->parentUID) push(thisUID[nextUID1]->0) push(NEW nextUID12->1)
      [2]      pop(1:nextUID12->thisUID)  pop(0:nextUID1->parentUID) push(thisUID[nextUID12]->0) push(NEW nextUID13->1)

            //每一段的最后一条数据的next记录，都需要指向null。不处理的话，在拖动积木时，会使原本分离的积木发生黏连。

            //输出结果

      递归结构的处理：

         1. pop两次
               a. 从堆栈中弹出之前存入的nextUID，赋值给thisUID
               b. 从堆栈中弹出之前存入的thisUID，赋值给parentUID

         2. push三次
               a. 将当前的thisUID压入堆栈
               b. 将当前的thisUID压入堆栈
               c. 将当前生成的nextUID压入堆栈，作为下一个积木的thisUID

         3. 递归处理积木数据

         4. 收尾
               a. 从堆栈中弹出一个数据，丢弃
               b. 从堆栈中弹出一个数据，丢弃
               c. 将递归调用前生成的thisUID压入堆栈，作为下一个积木的parentUID
               c. 将递归调用前生成的nextUID压入堆栈，作为下一个积木的thisUID



       顺序结构的处理：
         1. pop两次
               a. 从堆栈中弹出之前存入的nextUID，赋值给thisUID
               b. 从堆栈中弹出之前存入的thisUID，赋值给parentUID

         2. push两次
               b. 将当前的thisUID压入堆栈
               c. 将当前生成的nextUID压入堆栈，作为下一个积木的thisUID

         3. 处理积木数据

       补充：
            HATS类型积木和自制积木在处理结束后，不需要对$UIDS进行pop操作，因为后面不会有任何代码了。

   ******************************************************************************************************************************************************************/

   /*****************************************************************************************************************************************************************
    
      parseSpecialBlocks主要处理有分支的积木，比如自定义函数、事件、循环和条件判断等。
      
      无分支的积木，直接在default中调用parseGeneralBlocks。
    
   ******************************************************************************************************************************************************************/
   private function parseSpecialBlocks($arrCode)
   {
      $nCodeLength=count($arrCode);		//文本代码拆分成数组后的长度
      for($i=0;$i<$nCodeLength;$i++)
      {
         $opcode=$arrCode[$i];
         switch($arrCode[$i])
         {
            case "SELFDEFINED_FUNCTION":			//自制积木定义的处理

               /***********************************************************************
                  array(11)
                  (
                     [0]=> "SELFDEFINED_FUNCTION"
                     [1]=> "void 如果_a_就显示_b_否则显示_c_"
                     [2]=> "("
                     [3]=> "BOOL a"
                     [4]=> ","
                     [5]=> "VAR b"
                     [6]=> ","
                     [7]=> "VAR c"
                     [8]=> ")"
                     [9]=> "{"
                     [10]=> "}"
                  )
               ***********************************************************************/
               $nextUID=UID();

               $thisUID=array_pop($this->UIDS);					//出栈：thisUID
               $parentUID=array_pop($this->UIDS);				//出栈：parentUID
               if($thisUID==NULL) {$parentUID=$thisUID;$thisUID=UID();}

               array_push($this->UIDS,$thisUID);				//入栈：thisUID
               array_push($this->UIDS,$nextUID);				//入栈：nextUID

               $this->bTOPLEVEL="false";						//防止后续积木的toplevel为true，导致显示异常。

               $prototypeUID=UID();

               //获取函数名
               $strFunctionName=str_replace(" ","",ltrim($arrCode[++$i],"void "));

               $this->arrCurrentSDFBlock=$strFunctionName;				//记录当前属于哪个自制积木，方便积木块中的积木使用本地变量
echo "SDFFFFFFFFFFFFFFFFFFFF ".$this->arrCurrentSDFBlock." BEGIN\n";
               //解析参数
               //$strCondition="";
               //$arrCondition=Array();
               //条件

               /***********************************************************************

                  为确保自制积木的自定义函数能够在其自身被解析创建前就能被调用，
                  在自制积木数据的拆分过程中，将自定义函数的参数另外存了一份，
                  因此该数据，就不需要再出现在当前数据中，也就可以少一个对参数的解析了。

               ***********************************************************************/
               $i++;							//跳过函数名

               /************************************************************************   
               //自制积木的参数格式之前已经解析过，所以这里就不需要处理了。
               //$nBraceCounter=1;
               while($i<$nCodeLength-1)	
               {
                  //$strCode=$arrCode[++$i];
                  //if($strCode=="(") $nBraceCounter++;
                  if($arrCode[++$i]==")") break;//$nBraceCounter--;
                  //if($nBraceCounter==0) break;
                  //$strCondition.=$strCode;
               }
               //$strCondition.=",";

               //$argCounter=preg_match_all("/((VAR)|(BOOL))\s+([^^]*?),/",$strCondition,$sdf_args);
               ************************************************************************/

               /***********************************************************************
               Array
               (
                   [func1_a_] => Array
                       (
                           [0] => func1 %s
                           [1] => Array
                               (
                                   [0] => VAR
                               )
                           [2] => Array
                               (
                                   [0] => a
                               )
                       )
                   [func2_b123__c_2_] => Array
                       (
                           [0] => func2 %s  %b
                           [1] => Array
                               (
                                   [0] => VAR
                                   [1] => BOOL
                               )
                           [2] => Array
                               (
                                   [0] => b123
                                   [1] => c_2
                               )
                       )
               )
               ***********************************************************************/

               $arrSDFConfig=isset($this->arrSelfDefinedFunctions[$strFunctionName])?$this->arrSelfDefinedFunctions[$strFunctionName]:NULL;
               if($arrSDFConfig==NULL) exit();//数据异常，没有继续的必要了。
               //echo "SDFConfig\n";
               //print_r($arrSDFConfig);

               $proccode    = $arrSDFConfig[0];			//获取已经解析的proccode
               $arrArgTypes = $arrSDFConfig[1];			//获取已经解析的变量类型
               $arrArgNames = $arrSDFConfig[2];			//获取已经解析的变量名
               $arrArgUIDS  = isset($this->arrSelfDefinedFunctionArgs[$strFunctionName])?$this->arrSelfDefinedFunctionArgs[$strFunctionName]:NULL;//参数的UID。无参数即为NULL。

               $argUIDS=Array();

               $nSDFConfigLength=count($arrSDFConfig[1]);//[1]为变量类型；[2]为变量名

               $input_str="";
               $arrInputUIDS=Array();
               $argumentids_str="[";
               $arguments_str="[";
               $argumentdefaults="[";

               if($nSDFConfigLength>0)	//有参数
               {
                  //$this->arrSelfDefinedFunctionArgs=Array();//新的自制积木，清零。
                                                    //自制积木中的参数变量，只有在自制积木定义块中才有效，所以当第二个自制积木出现时，原有参数即刻失效。
                  //$sdf_args[1] //参数类型
                  //$sdf_args[4] //参数名
                  for($j=0;$j<$nSDFConfigLength;$j++)		//构建参数积木
                  {
                     //echo "参数定义中";
                     $argChildUIDS=UID();

                     array_push($this->Blockly,'{"id": "'.$argChildUIDS.'", "opcode": "'.(($arrArgTypes[$j]=="VAR")?"argument_reporter_string_number":"argument_reporter_boolean").'", "inputs": {}, "fields": { "VALUE": { "name": "VALUE", "value": "'.$arrArgNames[$j].'" }}, "next": null, "topLevel": false, "parent": "'.$prototypeUID.'", "shadow": true }');

                     //$arrInputUIDS[$j]=isset($this->arrSelfDefinedFunctionArgs[$strFunctionName][$arrSDFConfig[2][$i]])?$this->arrSelfDefinedFunctionArgs[$strFunctionName][$arrSDFConfig[2][$j]]:UID();
                     if($j>0)
                     {
                        //$proccode_str.="";
                        $input_str.=","; 
                        $argumentids_str.=",";
                        $arguments_str.=",";
                        $argumentdefaults.=",";
                     }

                     //$arrArgName[$j]='_'.$strArgName.'_';	//积木proccode的拼接处理准备
                     //$arrArgType[$j]=(($sdf_args[1][$j]=="VAR")?' %s ':' %b ');

                     //$proccode_str	.=(($sdf_args[1][$j]=="VAR")?'%s':'%b');
                     $input_str	.='"'.$arrArgUIDS[$arrArgNames[$j]].'": {"name": "'.$arrArgUIDS[$arrArgNames[$j]].'","block": "'.$argChildUIDS.'", "shadow": "'.$argChildUIDS.'"}';
                     $argumentids_str	.='\"'.$arrArgUIDS[$arrArgNames[$j]].'\"';
                     $arguments_str	.='\"'.$arrArgNames[$j].'\"';
                     $argumentdefaults.=(($arrArgTypes[$j]=="VAR")?'\"\"':'\"false\"');

                     //自制积木的变量。
                     //$this->arrSelfDefinedFunctionArgs[$strFunctionName][$strArgName]=$arrInputUIDS[$j];//Array("自制积木名"=>Array("变量名"=>变量UID))
                  }
               }

               //var_dump($this->arrSelfDefinedFunctionArgs);
               $argumentids_str.="]";
               $arguments_str.="]";
               $argumentdefaults.="]";
               //$proccode_str.="";

               //$strFunctionName_format=trim(str_replace($arrArgName,$arrArgType,$strFunctionName));//积木proccode的替换
               //$this->arrSelfDefinedFunctions[$strFunctionName]=Array($strFunctionName_format,$sdf_args[1],$sdf_args[4]);

               $i++;						//跳过一个{

               //subtrack
               $nBraceCounter=1;
               $childFunc=Array();
               while($i<$nCodeLength-1)
               {
                  $strCode=$arrCode[$i++];
                  if($strCode=="{")    $nBraceCounter++;
                  if($strCode=="}")    $nBraceCounter--;
                  if($nBraceCounter==0) break;		//计数器回到默认状态，说明这个循环可以结束了。
                  $childFunc[]=$strCode;
               }
               						//$i不退1

               //var_dump( $childFunc);
               //array_shift($childFunc);
               //array_pop($childFunc);

               $substackUID="";
               if(count($childFunc)>0)
               {
                  $substackUID=UID();
                  array_push($this->UIDS,$thisUID);		//入栈：thisUID
                  array_push($this->UIDS,$substackUID);		//入栈：nextUID

                  $this->parseSpecialBlocks($childFunc);			//递归处理子程序集
                  //$this->arrSelfDefinedFunctionArgs=NULL;		//数据不需要清掉，因为在调用时需要用到。
               }

               $lastBlock=array_pop($this->Blockly);
               if($lastBlock){
                  $jsonBlock=json_decode($lastBlock);
                  if(isset($jsonBlock->{'next'}))	//有效积木，强制修改next数据。
                  {
                     $jsonBlock->{'next'}=NULL;
                     array_push($this->Blockly,json_encode($jsonBlock));		//jsonDecode和jsonEncode后的数据，会删掉多余的空格。
                  }
                  else
                     array_push($this->Blockly,$lastBlock);				//即使解析出错，也原样保存，便于后续排错。
               }

echo "SDFFFFFFFFFFFFFFFFFFFF ".$this->arrCurrentSDFBlock." END \n";
               $this->arrCurrentSDFBlock="";			//自制积木定义结束，就不需要通过积木名字查局部变量信息了。
		//Hats积木块的主信息
               //$custom_block_UID=UID();
               array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "procedures_definition","inputs": {"custom_block": {"name": "custom_block","block": "'.$prototypeUID.'","shadow": "'.$prototypeUID.'"}}, "fields": {},"next": '.($substackUID==""?'null':'"'.$substackUID.'"').',        "topLevel": true,        "parent": null,        "shadow": false    }');
               array_push($this->Blockly,'{"id": "'.$prototypeUID.'","opcode": "procedures_prototype","inputs": {'.$input_str.'},"fields": {},"next": null,"topLevel": false,        "parent": "'.$thisUID.'",        "shadow": true,        "mutation": {            "tagName": "mutation",            "children": [],            "proccode": "'.$proccode.'",            "argumentids": "'.$argumentids_str.'",            "argumentnames": "'.$arguments_str.'",            "argumentdefaults": "'.$argumentdefaults.'",            "warp": "false"        }    }');


               //array_pop($this->UIDS);				//出栈：nextUID
               //array_pop($this->UIDS);				//出栈：parentUID	//已返回，需要将上一个积木的thisuid（即当前的parentUID）删除
               //array_push($this->UIDS,$nextUID);		//入栈：nextUID

               break;



            /*********************************************************************************
                这三个都是HATS类型积木，而代码段一开始就对$this->UIDS进行了初始化，
                所以为保证代码的统一，不直接生成$thisUID,$parentUID,$nextUID，而还是从$this->UIDS里取。
                在需要生成子代码时，需要将它们再逐个压入。
            **********************************************************************************/

            //这三个不带参数的HAT积木，有相同的结构
            case "event_whenflagclicked":				//当绿旗被点击
            case "event_whenthisspriteclicked":				//当角色被点击
            case "control_start_as_clone":				//当克隆启动时

            //这三个带fields参数的HAT积木
            case "event_whenkeypressed":				//当按下某按键
            case "event_whenbackdropswitchesto":			//当背景被切换
            case "event_whenbroadcastreceived":				//当接收到广播消息

            //这一个带fields参数和inputs参数的HAT积木
            case "event_whengreaterthan":				//当参数大于

               $thisUID=array_pop($this->UIDS);				//先出 thisUID
               $parentUID=array_pop($this->UIDS);			//后出 parentUID
               $nextUID=UID();						//生成 nextUID

               $strValue1=$strValue2='';

               $i=4;							//若无参数，从此开始获取子积木
               $nBraceCounter=1;

               if($arrCode[2]!=")")					//此处如果是“)”，就表示没有任何参数
               {
                  $strValue1=trim($arrCode[2],"\"");			//这几个HATS，第一个参数都是fields类型，不会有复杂的公式出现。

                  if($arrCode[3]==",")					//带参数的HATS类都有这个特征
                  {
                     $strValue2='';
                     $i=4;						//第二个参数从这里开始。

                     while($i<$nCodeLength)				//从第二个参数开始，所有数据保存在一起。
                     {
                        $chCH=$arrCode[$i++];
                        if($chCH=='{') break;				//遇到“{”就结束
                        $strValue2.=trim($chCH);	
                     }
                     $strValue2=substr($strValue2,0,-1);		//清掉一个“)”
                  }
                  else
                  {
                     $i=5;						//如果只有一个参数，那么后面子积木代码从此处开始检测
                  }
               }

               $childFunc=Array();					//获取子积木数据
               while($i<$nCodeLength)
               {
                  $chCH=$arrCode[$i++];
                  if($chCH=='{') $nBraceCounter++;
                  else if($chCH=='}') $nBraceCounter--;

                  if($nBraceCounter==0) break;
                  $childFunc[]=trim($chCH);	
               }
               if(count($childFunc)==0) $nextUID=NULL;			//该段只有当前这一个积木，则nextUID为null。

               if($nextUID!=NULL)					//不为NULL，也就意味着该HATS有子积木需要处理
               {
                  array_push($this->UIDS,$parentUID);			//先入 parentUID，直接用NULL也可以。
                  array_push($this->UIDS,$thisUID);			//后入 thisUID
                  array_push($this->UIDS,$nextUID);			//最后 nextUID

                  $this->bTOPLEVEL="false";				//子积木的topLevel都为false。

                  $this->parseSpecialBlocks($childFunc);			//递归处理子程序集

                  $lastBlock=array_pop($this->Blockly);
                  if($lastBlock){
                     $jsonBlock=json_decode($lastBlock);
                     if(!isset($this->isHATS[$jsonBlock->{'opcode'}]))			//非HATS类，强制修改next数据。
                     {
                        $jsonBlock->{'next'}=NULL;
                        array_push($this->Blockly,json_encode($jsonBlock));		//jsonDecode和jsonEncode后的数据，会删掉多余的空格。
                     }
                     else
                        array_push($this->Blockly,$lastBlock);				//即使解析出错，也原样保存，便于后续排错。
                  }
               }

               $arrFieldsInfo=isset($this->arrArgumentConfig[$opcode])?$this->arrArgumentConfig[$opcode]:NULL;	//获取积木的配置信息

               $strInputs='';
               $strFields='';

               if($arrFieldsInfo)					//获取到配置信息（无参数，则相关数据为空，下面有处理。）
               {
                  $nINPUTS=count($arrFieldsInfo['inputs']);

                  $arrValue=Array($strValue2);

                  if($nINPUTS>1)					//存在多个inputs时，对相关参数进行拆分
                     $arrValue=explode(",",$strValue2);			//当参数里有函数random的调用时，这个拆分会有问题。但目前inputs最多也就一个，以后再调整。
               
                  for($g=0;$g<$nINPUTS;$g++)
                  {
                     $strShadowUID=UID();

                     //生成参数积木，返回UID 
 
                     $strArgument=$arrValue[$g];			//拼接成字符串

                     echo "解析前的参数：".$strArgument;
                     if(!is_numeric($strArgument))					//非纯数字的参数，利用RPN算法进行分解。
                     {
                        $arg=NULL;
                        if($this->rpn_calc-> init($strArgument))			//将四则混合运算字符串交由RPN来完成解析
                           $arg=$this->rpn_calc->toScratchJSON();			//生成符合Scratch3.0要求的数组数据
                        if($arg==NULL) $parsedArgData[$i]=$strArgument;		//如因括号不匹配之类的问题导致解析失败，则直接使用，因为可能是关键词。
                        else
                        {
                           $parsedArgData[$g]=$arg;				//解析成功，返回经RPN解析后的四则不混合运算数据
                        }
                     }
                     else $parsedArgData[$g]=trim($strArgument);			//纯数字参数，注意去除空格。

                     $arrChildUID=$this->parseCalculationExpression($arrFieldsInfo["inputs"][$g],$parsedArgData[$g],$thisUID); //解析的过程中，也会创建相应的积木数据，最终返回UID

                     //只要是文本或数字的参数，都要有Shadow，这个shadow不在parseCalculationExpression里创建。

                     if($arrChildUID[1]!=NULL)
                     {
                     //VAR类型参数需要补一个shadow，BOOL类型不需要。
                     array_push($this->Blockly,'{"id": "'.$strShadowUID.'","opcode": "'.$arrFieldsInfo["inputs"][$g][1].'","inputs": {},"fields": {"'.$arrFieldsInfo["inputs"][$g][2].'": {"name": "'.$arrFieldsInfo["inputs"][$g][2].'","value": "10"  }},"next": null,"topLevel": false,"parent":null,"shadow": true}');//"parent": "'.$thisUID.'","shadow": true}');
                     //拼接主积木的参数数据
                     $strInputs.=($g>0?',':'') . ' "'.$arrFieldsInfo["inputs"][$g][0].'": { "name": "'.$arrFieldsInfo["inputs"][$g][0].'", "block": "'.$arrChildUID[0].'", "shadow": "'.$strShadowUID.'"  }';
                     }
                     else
                     {
                        //拼接主积木的参数数据
                        $strInputs.=($g>0?',':'') . ' "'.$arrFieldsInfo["inputs"][$g][0].'": { "name": "'.$arrFieldsInfo["inputs"][$g][0].'", "block": "'.$arrChildUID[0].'", "shadow": "'.$arrChildUID[0].'" }';
                     }
                  }

                  $nFIELDS=count($arrFieldsInfo['fields']);
                  for($f=0;$f<$nFIELDS;$f++)
                  {
                     if($f>0) $strFields.=" , ";
                     $strFields.= '"'.$arrFieldsInfo['fields'][$f][0].'": { "name": "'.$arrFieldsInfo['fields'][$f][0].'",  "value": "'.$strValue1.'" '.(isset($arrFieldsInfo["fields"][$f][1])?',"variableType": "'.$arrFieldsInfo["fields"][$f][1].'"':'').' }' ;

                  }
               }
               //Hats积木块的主信息
               //array_push($this->Blockly,'{"id": "'.$thisUID.'", "opcode": "'.$opcode.'",  "inputs": {},  "fields":  { "KEY_OPTION": { "name": "KEY_OPTION",  "value": "'.$keyPressed.'"  } },  "next": "'.$nextuid.'",  "topLevel": true,  "parent": null,  "shadow": false}' );

               array_push($this->Blockly,'{"id": "'.$thisUID.'", "opcode": "'.$opcode.'",  "inputs": {'.$strInputs.'},  "fields":  { '.$strFields.' },  "next": "'.$nextUID.'",  "topLevel": true,  "parent": null,  "shadow": false}' );

            break;


             //这个非HAT积木，比较特别
            //具有包含作用的积木  if...then... if...else...  do...while,以及自定义模块
            case "do":
print_r($arrCode);

               $thisUID=array_pop($this->UIDS);			//出栈：thisUID
               $parentUID=array_pop($this->UIDS);		//出栈：parentUID
               if($thisUID==NULL) {$parentUID=$thisUID;$thisUID=UID();}
               $nextUID=UID();

               $TOPLEVELSTATUS= $this->bTOPLEVEL;
               $this->bTOPLEVEL="false";

               //subtrack
               $nBraceCounter=1;
               $i+=2;						//当前为do，$i++为{
               //echo $i."ffffffffff\n";
               $childFunc=Array();
               $nCheckChildFunc=$i;
               while($i<$nCodeLength-1)
               {
                  $strCode=trim($arrCode[$i++]);
                  if($strCode=="{")    $nBraceCounter++;
                  if($strCode=="}")    $nBraceCounter--;
                  if($nBraceCounter==0) break;		//计数器回到默认状态，说明这个循环可以结束了。
                  $childFunc[]=$strCode;
               }

//echo "wait: $i  $nCheckChildFunc ".($i-$nCheckChildFunc)."\n";
               $bWAIT=false;					//区分control_wait_until和control_repeat_until
               if($i-$nCheckChildFunc==1)
               {
                  $bWAIT=true;
                  $childFunc=Array();
               }

               $i+=2;						//$i当前为while  $i++为(

               $substackUID="";

               if(!$bWAIT && !empty($childFunc[0]))		//是“重复执行直到<条件>”积木，需要解析存在的SUBSTACK
               {
                  $substackUID=UID();
                  array_push($this->UIDS,$thisUID);		//入栈：thisUID	//将进入下一层，需要多压入一次，以便在返回时仍保留一份数据
                  array_push($this->UIDS,$substackUID);		//入栈：nextUID

                  $this->parseSpecialBlocks($childFunc);			//递归处理子程序集

                  $lastBlock=array_pop($this->Blockly);				//取最后一条积木数据
                  if($lastBlock){
                     $jsonBlock=json_decode($lastBlock);			//文本转JSON格式
                     if($jsonBlock){
                        $jsonBlock->{'next'}=NULL;				//由于刚才是对SUBSTACK进行了处理，现在SUBSTACK结束，那么SUBSTACK中的最后一块积木的next要置为NULL。
                        array_push($this->Blockly,json_encode($jsonBlock));	//注意：jsonDecode和jsonEncode后的数据，会删掉多余的空格。
                     }
                     else
                        array_push($this->Blockly,$lastBlock);			//即使转JSON格式出错，也原样保存回去，便于后续排错。
                  }
                  //else{}  //无有效数据，就不处理了。
               }

               $strCondition="";
               $nBraceCounter=1;
               while($i<$nCodeLength-1)
               {
                  $strCode=$arrCode[$i++];
                  if($strCode=="(")    $nBraceCounter++;
                  if($strCode==")")    $nBraceCounter--;
                  if($nBraceCounter==0) break;		//计数器回到默认状态，说明这个循环可以结束了。
                  $strCondition .= $strCode;
               }

               echo "sss $i  [$strCondition] \n";

               //$strCondition=$arrCode[$i];
               //$strCondition=trim($strCondition,"!");

               if($strCondition[0]=='!') $strCondition=substr($strCondition,1);	//去掉一个可能存在的!

               if($strCondition=="1")				//重复执行
               {
                  //$repeat_opcode="control_forever";
                  array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "control_forever","inputs": {"SUBSTACK": {"name": "SUBSTACK","block": "'.$substackUID.'","shadow": null}},"fields": {},"next": '.($nextUID?'"'.$nextUID.'"':'null').',  "topLevel": '.$TOPLEVELSTATUS.', "parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').',  "shadow": false}');
               }
               else
               {
                  $condition_input='';
                  $condition_input_id='';
                  $condition_returnArr=Array();

                  if($strCondition)					//解析存在的判断条件
                  {
                     if(isset($this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock][$strCondition]))	//对自制积木中的本地变量直接引用
                     {
                        $arrChildUID=Array(UID(),NULL);
                        array_push($this->Blockly, '{"x":"2","id": "'.$arrChildUID[0].'","opcode":"argument_reporter_boolean","inputs": {},"fields": {"VALUE": {"name": "VALUE","value": "'.$strCondition.'"}},"next": null,"topLevel": false,"parent":  "'.$thisUID.'","shadow":false}');
                        $condition_input=' "CONDITION":{  "name": "CONDITION",   "block": "'.$arrChildUID[0].'",    "shadow": null}'; //链接条件数据
                     }
                     else
                     {
                        $arrMainProcedure=$this->rpn_logic->init($strCondition);	//进行逻辑表达式解析
                        if($arrMainProcedure!=NULL)
                        {
                           //$mpCounter=count($arrMainProcedure);

                           //echo " parsedLLLLLLLLLLLLLLLLLLL $thisUID\n";
                           $arrChildUID=$this->parseLogicExpression($arrMainProcedure,$thisUID);	//头部积木UID要用。

                           //构建条件数据
                           if(isset($arrChildUID[0]) && $arrChildUID[0])
                           {
                              $condition_input=' "CONDITION":{  "name": "CONDITION",   "block": "'.$arrChildUID[0].'",    "shadow": null}'; //链接条件数据
                           }
                           else
                              $condition_input=' "CONDITION":{  "name": "CONDITION",   "block": null,    "shadow": null}'; //条件异常
                        }
                        else
                        {
                           $condition_input=' "CONDITION":{  "name": "CONDITION",   "block": null,    "shadow": null}'; //条件异常
                        }
                     }
                  }
                  else	//当条件里表达错误时（比如在自定义积木之外使用其参数变量，变量名不存在以及表达式错误：Scratch不支持if(1){}这种表达），显示无条件状态。
                     $condition_input=' "CONDITION":{  "name": "CONDITION",   "block": null,    "shadow": null}'; //condition的shadow为null

                  if($bWAIT)					//等待
                  {

                     //echo "wait next uid: $nextUID \n";
                     //$repeat_opcode="control_wait_until";
                     array_push($this->Blockly,'{"id": "'.$thisUID.'",    "opcode": "control_wait_until",    "inputs": {  '.$condition_input.'  },    "fields": {},    "next": '.($nextUID?'"'.$nextUID.'"':'null').',  "topLevel": '.$TOPLEVELSTATUS.', "parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').', "shadow": false}');
                  }
                  else						//重复执行直到
                  {
                     //$repeat_opcode="control_repeat_until";
                     array_push($this->Blockly,'{"id": "'.$thisUID.'",    "opcode": "control_repeat_until",    "inputs": { "SUBSTACK": { "name": "SUBSTACK",  "block": '.($substackUID==""?'null':'"'.$substackUID.'"').', "shadow": null }, '.$condition_input.' }, "fields": {}, "next": '.($nextUID?'"'.$nextUID.'"':'null').',  "topLevel": '.$TOPLEVELSTATUS.', "parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').', "shadow": false}');
                  }
               }
/*

               $lastBlock=array_pop($this->Blockly);
               if($lastBlock){
                  $jsonBlock=json_decode($lastBlock);
                  if($jsonBlock->{'next'}!=$thisUID)			//
                  {
//echo "5REMOVED NEXT:".$jsonBlock->{'next'}."\n".$lastBlock;
                     //$jsonBlock->{'next'}=NULL;
                     array_push($this->Blockly,json_encode($jsonBlock));		//jsonDecode和jsonEncode后的数据，会删掉多余的空格。
                  }
                  else
                     array_push($this->Blockly,$lastBlock);				//即使解析出错，也原样保存，便于后续排错。
               }
*/
               array_pop($this->UIDS);	//+
               array_pop($this->UIDS);				//出栈：parentUID	//已返回，需要将上一个积木的thisUID（即当前的parentUID）删除
               array_push($this->UIDS,$thisUID);//+
               array_push($this->UIDS,$nextUID);			//入栈：nextUID

            break;


             //这个非HAT积木，比较特别
            case "if":               //if(条件){第一分支}else{第二分支}

               /********************************************************************************************
                  条件可为逻辑表达式与算术表达式的混合
                  1>2 || 2>1
                  sensing_mousedown() && sensing_keypressed("a")
                  我的变量*2+3/4-abs(我的变量-3)==0

                  条件拆分：
                      1.按逻辑拆分
                      2.拆分后的非逻辑符部分，按算术拆分
                  
               ********************************************************************************************/


               $thisUID=array_pop($this->UIDS);			//取出当前，也即上一个主block生成的nextuid
               if($thisUID==NULL)  {$parentUID=$thisUID;$thisUID=UID();}
               $parentUID=array_pop($this->UIDS);
               $nextUID=UID();
               $TOPLEVELSTATUS= $this->bTOPLEVEL;
               $this->bTOPLEVEL="false";			//后续代码的toplevel必为false，true只在deal第一次进入时设置。


               $arrChildUID=Array();

               $arrChildBlocks1=Array();
               $arrChildBlocks2=Array();
               $arrChildBlocks=Array(Array(),Array());

               $strCondition="";
               $arrCondition=Array();

               //条件
               $i++;							//当前的拆分算法可以保证能够跳过第一个括号(
               $nBraceCounter=1;					//括号计数器加1
               while($i<$nCodeLength-1)				//获取逻辑表达式文本
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

               if($strCondition)					//解析存在的判断条件
               {

                  //echo "IFFFFFFFFFFFFFFF CONDITION:".$strCondition."\n";

                  /********************************************************************************************
                  逻辑表达式：
                   1>我的变量  && 4+3*我的变量>2 || sensing_mousedown()

                  拆分结果：
                  Array
                  (
                      [0] => Array						//标准的主逻辑判断在[0]，子逻辑判断在[1]
                          (
                              [0] => Array
                                  (
                                      [0] => >
                                      [1] => ID_0LNm6iJ98e,3RW`3yALu_DI
                                      [2] => 1
                                      [3] => 我的变量
                                  )
                              [1] => Array
                                  (
                                      [0] => >
                                      [1] => ID_xIIe97`pKP0pA@sx:|TM_DI
                                      [2] => 4+3*我的变量			//待进一步拆分的算术表达式
                                      [3] => 2					//不含运算符的文本可直接使用
                                  )
                              [2] => Array
                                  (
                                      [0] => &&
                                      [1] => ID_iOc~z@d}xU#M4U9eT8eR_DI
                                      [2] => ID_0LNm6iJ98e,3RW`3yALu_DI
                                      [3] => ID_xIIe97`pKP0pA@sx:|TM_DI
                                  )
                              [3] => Array
                                  (
                                      [0] => ||
                                      [1] => ID_u5WoHt]m?84g`7q0,C_N_DI		//[0][最后一组数据][1]，这个UID为所有积木的最底部的积木，需要传递给if
                                      [2] => ID_iOc~z@d}xU#M4U9eT8eR_DI
                                      [3] => ID_ed2aUBSC,A5NIn@I:Mw7_DI		//需要保存与本组数据[1]的对应关系，以确保parent指向正确的父积木块。
                                  )
                          )

                      [2] => Array						//函数调用在[2]
                          (
                              [0] => Array					//***********当前算法无法处理函数里的函数调用
                                  (
                                      [0] => sensing_mousedown			//被调用的函数的名字
                                      [1] => ID_ed2aUBSC,A5NIn@I:Mw7_DI		//UID
                                      [2] =>  					//函数可能存在的所有参数
                                  )
                          )
                  )
                  ********************************************************************************************/

                  $arrMainProcedure=$this->rpn_logic->init($strCondition);	//进行逻辑表达式解析

                  if($arrMainProcedure!=NULL)					//是有效的逻辑表达式
                  {
                     //echo "有效条件语句\n";

                     //$mpCounter=count($arrMainProcedure);

                     /**********************************************************************
                        逻辑表达式为：
                        sensing_coloristouchingcolor( "#ff0000" ,"#ff00ff" )  ||  sensing_keypressed( "space" ) || sensing_mousedown()  

                        解析后的数据（$arrMainProcedure）：

                        Array
                        (
                            [0] => Array
                                (
                                    [0] => Array
                                        (
                                            [0] => ||
                                            [1] => ID_ZGxPz4DTxyBeX3qqC?wg_DI
                                            [2] => ID_Z$e.D0hdEkj389PV`Von_DI
                                            [3] => ID_4{02KyiRQMZ;ra6EZfT:_DI
                                        )
                                    [1] => Array
                                        (
                                            [0] => ||
                                            [1] => ID_Y[{GMKc{]4`J_aCOpXnC_DI		//这个UID需要在解析后返回到主调积木
                                            [2] => ID_ZGxPz4DTxyBeX3qqC?wg_DI
                                            [3] => ID_7ZCPBO92N4x?}To2;7Ec_DI
                                        )
                                )
                            [2] => Array
                                (
                                    [0] => Array
                                        (
                                            [0] => sensing_coloristouchingcolor
                                            [1] => ID_Z$e.D0hdEkj389PV`Von_DI
                                            [2] => "#ff0000","#ff00ff"
                                        )
                                    [1] => Array
                                        (
                                            [0] => sensing_keypressed
                                            [1] => ID_4{02KyiRQMZ;ra6EZfT:_DI
                                            [2] => "space"
                                        )
                                    [2] => Array
                                        (
                                            [0] => sensing_mousedown
                                            [1] => ID_7ZCPBO92N4x?}To2;7Ec_DI
                                            [2] => 
                                        )
                                )
                        )

                        也可能出现这种情况：

                        if(  ( 1 >  ( 2 +  ( 3 * 我的变量 )  )  )  ){}

                        解析后数据：
                        Array
                        (
                            [0] => Array
                                (
                                )

                            [1] => Array
                                (
                                    [0] => Array
                                        (
                                            [0] => >
                                            [1] => ID_f$eJ~`xB6I{@iz]y_?Q__DI
                                            [2] => 1
                                            [3] => 2 +(3 * 我的变量)
                                        )

                                )

                        )

                     **********************************************************************/

                     $arrChildUID=Array(NULL,NULL);//实际只要一个，暂时这样，保证数据结构的完整性。
                     if(empty($arrMainProcedure[0]) && empty($arrMainProcedure[1]) && empty($arrMainProcedure[2]))	//全为空，则直接创建
                     {
                        if(isset($this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock][$strCondition]))	//对自制积木中的本地变量直接引用
                        {
                           $arrChildUID=Array(UID(),NULL);
                           array_push($this->Blockly, '{"x":"1","id": "'.$arrChildUID[0].'","opcode":"argument_reporter_boolean","inputs": {},"fields": {"VALUE": {"name": "VALUE","value": "'.$strCondition.'"}},"next": null,"topLevel": false,"parent":  "'.$thisUID.'","shadow":false}');
                        }
                     }
                     else												//如果参数本身就是一个函数调用，则只有$arrMainProcedure[2]有数据。
                        $arrChildUID=$this->parseLogicExpression($arrMainProcedure,$thisUID);	//头部积木UID要用。
              
                     /**********************************************************************
                        逻辑表达式积木生成后，返回底部积木的UID

                        Array
                        (
                            [0] => "ID_Y[{GMKc{]4`J_aCOpXnC_DI"		//这个UID成功返回到主调积木
                            [1] => "ID_Y[{GMKc{]4`J_aCOpXnC_DI"
                        )

                     **********************************************************************/

                     //构建条件数据
                     if(isset($arrChildUID[0]) && $arrChildUID[0])
                     {
                        $condition_input=' "CONDITION":{  "name": "CONDITION",   "block": "'.$arrChildUID[0].'",    "shadow": null}'; //链接条件数据
                     }
                     else
                        $condition_input=' "CONDITION":{  "name": "CONDITION",   "block": null,    "shadow": null}'; //条件异常
                  }
                  else					//无有效逻辑表达式。当条件里表达错误时（比如在自定义积木之外使用其参数变量，变量名不存在以及表达式错误：Scratch不支持if(1){}这种表达），显示无条件状态。
                     $condition_input=' "CONDITION":{  "name": "CONDITION",   "block": null,    "shadow": null}'; //condition的shadow为null
               }
               else					//无条件语句
                  $condition_input=' "CONDITION":{  "name": "CONDITION",   "block": null,    "shadow": null}'; //condition的shadow为null

               //第一分支：if
               if($arrCode[++$i]=="{")				//由于空格和回车已经被屏蔽，所以if(条件)后必定是{或者下一个命令
               {
                  $nBraceCount=1;
                  $i++;
                  while($i<$nCodeLength)					//析出SUBSTACK
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
                  while($i<$nCodeLength)					//if后面没有{}，则以;结束
                  {
                     $strCode=$arrCode[$i++];
                     $arrChildBlocks1[]=$strCode;
                     if($strCode==";")    break;
                  }
               }
               $substack1='';					//构建SUBSTACK的JSON数据
                  ////echo "xxxxxxxxxxxxxxxxxx\n";
                  ////var_dump($arrChildBlocks1);
               if(count($arrChildBlocks1)>0)
               {

                  $substackUID=UID();
                  array_push($this->UIDS,$thisUID);		//入栈：thisUID
                  array_push($this->UIDS,$substackUID);		//入栈：nextUID

                  $substack1=', "SUBSTACK":{"name": "SUBSTACK","block": "'.$substackUID.'","shadow": null}';//开始位置要加“,”。

                  //$nextUID=UID();//nextuid和substackUID要分清。。。nextuid已经被使用，那么主积木的nextuid就应该重新生成一个。

                  $this->parseSpecialBlocks($arrChildBlocks1);			//递归处理子程序集



                  $lastBlock=array_pop($this->Blockly);				//取最后一条积木数据
                  if($lastBlock){
                     $jsonBlock=json_decode($lastBlock);			//文本转JSON格式
                     if($jsonBlock){
                        $jsonBlock->{'next'}=NULL;				//由于刚才是对SUBSTACK进行了处理，现在SUBSTACK结束，那么SUBSTACK中的最后一块积木的next要置为NULL。
                        array_push($this->Blockly,json_encode($jsonBlock));	//注意：jsonDecode和jsonEncode后的数据，会删掉多余的空格。
                     }
                     else
                        array_push($this->Blockly,$lastBlock);			//即使转JSON格式出错，也原样保存回去，便于后续排错。
                  }
                  //else{}  //无有效数据，就不处理了。

/*
                  $lastBlock=array_pop($this->Blockly);
                  if($lastBlock){
                     $jsonBlock=json_decode($lastBlock);
                     if(!isset($this->isHATS[$jsonBlock->{'opcode'}]))			//非HATS类，强制修改next数据。
                     {
//echo "6REMOVED NEXT:".$jsonBlock->{'next'}."\n".$lastBlock;
                        //$jsonBlock->{'next'}=NULL;
                        array_push($this->Blockly,json_encode($jsonBlock));		//jsonDecode和jsonEncode后的数据，会删掉多余的空格。
                     }
                     else
                        array_push($this->Blockly,$lastBlock);				//即使解析出错，也原样保存，便于后续排错。
                  }
*/

               }

               //第二分支:else

               $i2=$i-1;						//偏移量退1

               $bIFELSE=false;						//检测是否有else
               while($i2<$nCodeLength-1)				//检测{开始位
               {
                  $strCode=trim($arrCode[++$i2]);
                  if($strCode=="") continue;				//空行，继续
                  else if($strCode=='else'){$bIFELSE=true; break;}	//{，跳出while
                  else break;						//其他字符，跳出while
               }

               if($bIFELSE)
               {
                   $i=$i2;
               }

               //var_dump($arrCode);
               if(isset($arrCode[$i]) && $arrCode[$i]=="else")		//有else
               {
                  //echo "有ELSEEEEEEEEEEEEEEEEEEEE\n";

                  if($arrCode[$i+1]=="{")				//有括号{
                  {
                     $nBraceCount=1;
                     $i+=2;
                     while($i<$nCodeLength)				//析出SUBSTACK
                     {
                        $strCode=$arrCode[$i++];
                        if($strCode=="{")    $nBraceCount++;
                        if($strCode=="}")    $nBraceCount--;

                        if($nBraceCount==0) break;			//计数器回到默认状态，说明这个循环可以结束了。
                        $arrChildBlocks2[]=$strCode;
                     }
                  }
                  else							//无括号{
                  {
                     $i++;
                     while($i<$nCodeLength)				//else后面没有{}，则以;结束
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

                  $substackUID=UID();

                  array_push($this->UIDS,$thisUID);		//入栈：thisUID
                  array_push($this->UIDS,$substackUID);		//入栈：nextUID

                  $substack2=',"SUBSTACK2":{"name": "SUBSTACK2","block": "'.$substackUID.'","shadow": null}';//开始位置要加“,”。

                  $this->parseSpecialBlocks($arrChildBlocks2);			//递归处理子程序集

                  $lastBlock=array_pop($this->Blockly);				//取最后一条积木数据
                  if($lastBlock){
                     $jsonBlock=json_decode($lastBlock);			//文本转JSON格式
                     if($jsonBlock){
                        $jsonBlock->{'next'}=NULL;				//由于刚才是对SUBSTACK进行了处理，现在SUBSTACK结束，那么SUBSTACK中的最后一块积木的next要置为NULL。
                        array_push($this->Blockly,json_encode($jsonBlock));	//注意：jsonDecode和jsonEncode后的数据，会删掉多余的空格。
                     }
                     else
                        array_push($this->Blockly,$lastBlock);			//即使转JSON格式出错，也原样保存回去，便于后续排错。
                  }
                  //else{}  //无有效数据，就不处理了。

               }

               /*******************************************************************************************

                  有如下代码：

                    if( 1>2 ){
                       for(int i1 = 0; i1 < 步数; i1++ ){
         
                       }
                    }
                    if( 3>$  ){
                       for(int i2 = 0; i2 < 步数; i2++ ){
 
                       }
                    }

                  第2个if在创建积木数据时，需要确认上一条积木数据中的next是否为$thisUID，
                  如果不是，需要清掉上一条数据的next数据。

                  这条规则是否适用于所有积木，待测。

               *******************************************************************************************/

/*//在上面进行了处理，这里不需要了。
               $lastBlock=array_pop($this->Blockly);
               if($lastBlock){
                  $jsonBlock=json_decode($lastBlock);
                  if($jsonBlock->{'next'}!=$thisUID)			//非HATS类，强制修改next数据。
                  {
//echo "8REMOVED NEXT:".$jsonBlock->{'next'}."\n".$lastBlock;
                     //$jsonBlock->{'next'}=NULL;
                     array_push($this->Blockly,json_encode($jsonBlock));		//jsonDecode和jsonEncode后的数据，会删掉多余的空格。
                  }
                  else
                     array_push($this->Blockly,$lastBlock);				//即使解析出错，也原样保存，便于后续排错。
               }
*/

               //构建if-else的完整数据
               array_push($this->Blockly,'{"d":"if","id": "'.$thisUID.'","opcode": "control_if'.($bIFELSE?'_else':'').'","inputs": {'.$condition_input.' '.$substack1.' '.$substack2.'},"fields": {},"next": '.($nextUID?'"'.$nextUID.'"':'null').',"topLevel": '.$TOPLEVELSTATUS.', "parent": '.($parentUID?'"'.$parentUID.'"':'null').',"shadow": false}');

               $uid=array_pop($this->UIDS);			//出栈：nextUID
               array_pop($this->UIDS);				//出栈：parentUID	//已返回，需要将上一个积木的thisUID（即当前的parentUID）删除
               array_push($this->UIDS,$thisUID);			//入栈：nextUID
               array_push($this->UIDS,$nextUID);			//入栈：nextUID


            break;


            //这个非HAT积木，比较特别
            case "for":					//for循环比hats多了一个循环条件参数的解析。

               $thisUID=array_pop($this->UIDS);			//上一个积木生成的nextuid
               if($thisUID==NULL)  {$parentUID=$thisUID;$thisUID=UID();}
               $parentUID=array_pop($this->UIDS);		//上一个积木的thisUID
               //array_push($this->UIDS,$thisUID);		//因为要进入递归，所以压入两次

               $nextUID=UID();

               //array_push($this->UIDS,$thisUID);

               $nLOOP=-1;					//循环次数
               $strLoopCondition='';
               $nBraceCounter=0;

               $i++;						//到下一个数据：(
               while($i<$nCodeLength-1)			//获取循环次数表达：(int i=0;i<10;i++)
               {
                  $strCode=$arrCode[$i++];
                  if($strCode=="{") {$nBraceCounter=1;break;}	//遇到{就结束，此时，$i指向下一个符号的位置。
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
                           echo "条件结果：";
                           var_dump($arrLoopCondition);
                           $arrChildUIDX=$this->parseCalculationExpression(Array("NUM","math_number","NUM"),$arrLoopCondition,$thisUID);
                        }
                        else								//拆分失败
                        {
                           if(isset($this->arrVariableUIDS[trim($arrLoopCondition)]))//in_array(trim($arrLoopCondition),$this->arrVariables) )	//参数是已定义的变量，生成该变量的积木块，此处不需要shadow，shadow由repeat自己生成。
                           {
                              $arrChildUIDX[0]=UID();
                              array_push($this->Blockly, '{"x":"2","id": "'.$arrChildUIDX[0].'","opcode": "data_variable","inputs": {},"fields": {"VARIABLE": {"name": "VARIABLE","id": "'.UID().'","value": "'.trim($arrLoopCondition).'","variableType": ""}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": false}');
                           }
                           else if(isset($this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock][trim($m[4][0])]))
                           {
                              $arrChildUIDX[0]=UID();
                              array_push($this->Blockly, '{"x":"1","id": "'.$arrChildUIDX[0].'","opcode":"argument_reporter_string_number","inputs": {},"fields": {"VALUE": {"name": "VALUE","value": "'.trim($m[4][0]).'"}},"next": null,"topLevel": false,"parent":  "'.$thisUID.'","shadow":false}');
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

               while($i<$nCodeLength-1)
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
                  $substackUID=UID();			//生成包含的积木的下一个积木的UID
                  array_push($this->UIDS,$thisUID);
                  array_push($this->UIDS,$substackUID);		//压入一次nextuid

                  $this->parseSpecialBlocks($childFunc);			//递归调用处理子程序集

                  $lastBlock=array_pop($this->Blockly);				//取最后一条积木数据
                  if($lastBlock){
                     $jsonBlock=json_decode($lastBlock);			//文本转JSON格式
                     if($jsonBlock){
                        $jsonBlock->{'next'}=NULL;				//由于刚才是对SUBSTACK进行了处理，现在SUBSTACK结束，那么SUBSTACK中的最后一块积木的next要置为NULL。
                        array_push($this->Blockly,json_encode($jsonBlock));	//注意：jsonDecode和jsonEncode后的数据，会删掉多余的空格。
                     }
                     else
                        array_push($this->Blockly,$lastBlock);			//即使转JSON格式出错，也原样保存回去，便于后续排错。
                  }
                  //else{}  //无有效数据，就不处理了。

                  $substack=',"SUBSTACK": { "name": "SUBSTACK", "block": "'.$substackUID.'", "shadow": null } ';
               }

               $childUID=UID();

               //重复执行n次的参数设置
               if($nLOOP==-1)					//循环次数为0，表示循环条件为算术表达式
               {
                  //shadow
                  array_push($this->Blockly,  '{"f":"1","id": "'.$childUID.'", "opcode": "math_whole_number", "inputs": {}, "fields": { "NUM": { "name": "NUM", "value": "'.$nLOOP.'" } }, "next": null, "topLevel": false, "parent": null, "shadow": true}' );//"parent": "'.$thisUID.'", "shadow": true}' );
               	  //重复执行n次的主信息
                  //这里的next有个问题
                  array_push($this->Blockly,  '{"f":"2","id": "'.$thisUID.'", "opcode": "control_repeat", "inputs": { "TIMES": { "name": "TIMES", "block": "'.$arrChildUIDX[0].'", "shadow": "'.$childUID.'" } '.$substack.'}, "fields": {}, "next": '.(($nextUID)?'"'.$nextUID.'"':'null').', "topLevel": '.$TOPLEVELSTATUS.', "parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').', "shadow": false}' );
               }
               else						//直接用$nLOOP作为循环次数
               {
                  //次数
                  array_push($this->Blockly,  '{"f":"3","id": "'.$childUID.'", "opcode": "math_whole_number", "inputs": {}, "fields": { "NUM": { "name": "NUM", "value": "'.$nLOOP.'" } }, "next": null, "topLevel": false, "parent": "'.$thisUID.'", "shadow": true}' );//"parent": "'.$thisUID.'", "shadow": true}' );
               	  //重复执行n次的主信息
                  array_push($this->Blockly,  '{"f":"4","id": "'.$thisUID.'", "opcode": "control_repeat", "inputs": { "TIMES": { "name": "TIMES", "block": "'.$childUID.'", "shadow": "'.$childUID.'" } '.$substack.' }, "fields": {}, "next": '.(($nextUID)?'"'.$nextUID.'"':'null').', "topLevel": '.$TOPLEVELSTATUS.', "parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').', "shadow": false}' );
               }

/*
               $lastBlock=array_pop($this->Blockly);
               if($lastBlock){
                  $jsonBlock=json_decode($lastBlock);
                  if($jsonBlock->{'next'}!=$thisUID)			//
                  {
//echo "10REMOVED NEXT:".$jsonBlock->{'next'}."\n".$lastBlock;
                     //$jsonBlock->{'next'}=NULL;
                     array_push($this->Blockly,json_encode($jsonBlock));		//jsonDecode和jsonEncode后的数据，会删掉多余的空格。
                  }
                  else
                     array_push($this->Blockly,$lastBlock);				//即使解析出错，也原样保存，便于后续排错。
               }
*/

               array_pop($this->UIDS);			//弹出回调中返回的nextuid，舍弃。
               array_pop($this->UIDS);			//丢弃一次thisUID，也就是parentuid
               array_push($this->UIDS,$thisUID);	//下一个新积木的parentUID
               array_push($this->UIDS,$nextUID);	//下一个新积木的thisUID

               //$i--;
            break;

            //其他普通无包含关系的积木，在这里处理。
            default:					//其他以“;”结尾的普通函数调用的解析

               $childFunc=Array();
               $nRNCounter=0;
               while( $i<$nCodeLength)			//这里是对整个函数的剥离，所以不用考虑参数的多少，直接以;结束。
               {
                  $strCode=$arrCode[$i++];
                  if($nRNCounter==2) break;
                  if($strCode=="")
                  {
                      if($nRNCounter==0) $nRNCounter++;
                      else $nRNCounter=0;
                  }
                  else $childFunc[]=$strCode;			//先采集，再终止
                  if($strCode==";") break;
               }

               $i--;						//退1，这个不能少。

               $this->parseGeneralBlocks($childFunc);//,$bLastBlock);			//其他标准函数，都在parseGeneralBlocks里处理

               $this->bTOPLEVEL="false";

            break;
         }
      }
   }


   /************************************************************************************************************************

       定义自制积木
   
       代码解析转换的过程中，所有现有的积木的opcode，都已被保存在$arrArgumentConfig中，通过isset()就可以快速确认当前是否是积木；
       自制积木是由用户临时编写的，所以它的opcode（实际是proccode）并不在$arrArgumentConfig之中，因此，无法通过查询$arrArgumentConfig来确认当前是自制积木。

       想要让解析转换程序能够识别自制积木，就需要在解析前，先对自制积木的数据解析并保存，等解析开始时，再通过查字典来实现识别操作。

       定义自制积木的过程中，需要对解析后的自定义函数名和参数进行统一管理。

       解析后得到的自制积木的自定义函数名和参数配置，分别被保存在：

           1. $this->arrSelfDefinedFunctions（自定义函数名）

              某个字符串，只要在$this->arrSelfDefinedFunctions里能找到，就说明它是自制积木。

              数据格式：
                Array
                (
                    [func1_a__b_] => Array		//函数名，其中嵌入了参数，每个参数名前后都加一个“_”以示区分。
                        (
                            [0] => func1 %s %b		//proccode，在procedures_call里用来识别自制积木。%s表示数字或文本，%b表示布尔值。
                            [1] => Array		//参数类型
                                (
                                    [0] => VAR		//数字或文本
                                    [1] => BOOL		//布尔值：true/false
                                )
                            [2] => Array		//参数名
                                (
                                    [0] => a
                                    [1] => b
                                )
                        )
                )

           2. $this->arrSelfDefinedFunctionArgs（自定义函数参数）

              与变量一样，自制积木的参数变量，在整个项目中，都是唯一的，每个参数变量的UID，都可以通过函数名和变量名两个参数来查询。

              数据格式：
                Array
                (
                    [func1_a__b_] => Array			//key为自定义函数名
                        (
                            [a] => ID_{bmH46dyG17a:`?f9lfi_DI	//key为参数变量名，值为该参数的UID
                            [b] => ID_baOs9[LMnu#Iau5irS@D_DI
                        )
                )

   ************************************************************************************************************************/
   private function defineSelfDefinedFunction($arrSDFunction)
   {
      /**********************************************************************************************************
          $arrSDFunction数据格式：

          Array
          (
              [0] => void func_a__b_			//Scratch3.0中的自制积木是没有返回值的，所以在类C语言中，加一个void类型关键词。
              [1] => (
              [2] => VAR a				//为了简化，所有的数字和文本，都用VAR这个类型。
              [3] => ,
              [4] => BOOL b				//布尔值用BOOL。
              [5] => )
          )
          在Scratch3.0里，还有列表list和广播消息broadcast_msg这两种数据，我分别设成了LIST和MSG。这两种操作不能用于自制积木。

      **********************************************************************************************************/

      $nSDFLength=count($arrSDFunction);					//自定义函数数组长度

      $i=0;
      while($i<$nSDFLength-1)
      {
         //Scratch3.0里允许变量里有空格，但类C语言不允许，所以需要把空格剔除。
         $strFunctionName=str_replace(" ","",ltrim($arrSDFunction[$i++],"void "));	//截取函数名。

         $strArgumentDefinition="";         						//函数中参数的定义数据

         while($i<$nSDFLength-1)
         {
            $strCode=$arrSDFunction[++$i];						//一开始跳过一个“(”
            if($strCode==")") break;							//在函数定义里是不会有嵌套的小括号的，所以不需要进行匹配统计，遇到“)”就表示结束。
            $strArgumentDefinition.=$strCode;
         }
         $strArgumentDefinition.=",";							//加一个“,”方便正则匹配，最终数据形如： VAR a ,  BOOL b,

         /**********************************************************************************************************

             proccode数据生成算法，通过函数名中变量的占位情况，生成相应的proccode数据。例如：

               函数名：
                   func_a__b_

               参数名：
                   a
                   b

               参数类型：
                   VAR
                   BOOL

               替换算法：
                   str_replace("func2_a__b_",Array("_a_","_b_"),Array("%s","%b"));

               替换后数据：
                   func %s %b

         **********************************************************************************************************/

         $nADLength=preg_match_all("/((VAR)|(BOOL))\s+([^^]*?),/",$strArgumentDefinition,$sdf_args);//匹配参数类型($sdf_args[1])和名字($sdf_args[4])，并去掉多余的空格。

         $arrArgName=Array();							//保存变量名
         $arrArgType=Array();							//保存变量类型

         if($nADLength>0)							//有参数
         {
            for($j=0;$j<$nADLength;$j++)					//构建参数积木
            {
               //Scratch3.0里允许变量里有空格，但类C语言不允许，所以还是需要把空格剔除。		后期可以给本地变量加上角色名以区分。
               $strArgName=str_replace(" ","",$sdf_args[4][$j]);

               $arrArgName[$j]='_'.$strArgName.'_';				//给变量名添加前后缀。 a -> _a_
               $arrArgType[$j]=(($sdf_args[1][$j]=="VAR")?' %s ':' %b ');	//通过类型转换成格式字符。VAR -> %s，BOOL -> %b

               $this->arrSelfDefinedFunctionArgs[$strFunctionName][$strArgName]=UID();	//Array("自制积木名"=>Array("变量名"=>变量UID))
               /*******************************************************************

                  代码：
                      void func_a__b_(VAR a,BOOL b){}

                  解析后，$this->arrSelfDefinedFunctionArgs：
                      Array
                      (
                          [func_a__b_] => Array
                              (
                                  [a] => ID_xxxxxxxaxxxxxxxx_DI
                                  [b] => ID_xxxxxxxbxxxxxxxx_DI
                              )
                      )

               *******************************************************************/
            }
         }

         $strFunctionName_format=trim(str_replace($arrArgName,$arrArgType,$strFunctionName));				//通过批量替换，生成自制积木的proccode
         $this->arrSelfDefinedFunctions[$strFunctionName]=Array($strFunctionName_format,$sdf_args[1],$sdf_args[4]);	//装配数据
         /*******************************************************************

            代码：
                void func_a__b_(VAR a,BOOL b){}

            解析后，$this->arrSelfDefinedFunctions：
                Array
                (
                    [func_a__b_] => Array
                        (
                            [0] => func %s  %b
                            [1] => Array
                                (
                                    [0] => VAR
                                    [1] => BOOL
                                )
                            [2] => Array
                                (
                                    [0] => a
                                    [1] => b
                                )
                        )
                )

         *******************************************************************/
      }
   }

   /************************************************************************************************************************
   *
   **  拆分解析函数的参数（如果参数是公式，需要调用RPN来处理）
   **
   **  bLAST   true:最后一条数据，nextuid为空
   *
   *   传入单条记录
   *
   *
   *   此函数接收完整积木信息，
   *   如果参数非算术计算式/逻辑计算式，则直接生成积木
   *   否则调用算术计算/逻辑计算拆分操作进行拆分，返回拆分后数据（不可再拆分单元），调用算术计算/逻辑计算转换操作生成积木
   *
   *
   ************************************************************************************************************************/
   private function parseGeneralBlocks( $arrFuncData )//,$bLAST=false, $DesinatedTUID='',$DesinatedPUID='')
   {
      if(!isset($arrFuncData[0])) return NULL;

      $nFuncLength=count($arrFuncData);				//传入的数组的长度，遍历数组时需要

      $nextUID=UID();
      $thisUID=array_pop($this->UIDS);            	//之前的nextUID
      $parentUID=array_pop($this->UIDS);		//之前的thisUID

      array_push($this->UIDS,$parentUID);			//存一次thisUID，作为下一个的parentUID
      array_push($this->UIDS,$thisUID);			//存一次thisUID，作为下一个的parentUID
      array_push($this->UIDS,$nextUID);			//存一次nextUID，作为下一个的thisUID。

      $strActuralArg="";

print_r($arrFuncData);
//echo "66666666666666666666666666666666666666\n";
      switch($arrFuncData[0])
      {
         //主调函数的处理方法
         //格式：funName(arg);

      	 /**************这几个数学函数，实际调用的是operator_mathop积木，函数名是mathop的field参数名 ********/

         case "abs":
         case "ceiling":
         case "floor":
         case "sqrt":
         case "sin":
         case "cos":
         case "tan":
         case "atan":
         case "asin":
         case "acos":
         case "ln":
         case "log":
         case "e ^":
         case "10 ^":

//echo "loopsssssssssssssss\n";
//print_r($arrFuncData);
            //$strActuralArg=$arrFuncData[0];		//参数为函数名
            //$arrFuncData[0]="operator_mathop";		//这几个函数，实际都是由积木“operator_mathop”实现的。

//在这里替换太早了。
            //$strActuralArg="sin";		//参数为函数名

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
         case "motion_xposition":
         case "motion_yposition":
         case "motion_direction":
         case "motion_setrotationstyle":		//设置旋转方式			//一个不需要额外参数的特例

         //外观
         case "looks_goforwardbackwardlayers":
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
         case "looks_size":
         case "looks_costumenumbername":
         case "looks_backdropnumbername":
         case "looks_gotofrontback":			//上移/下移
         case "looks_changeeffectby":			//修改特效值为
         case "looks_seteffectto":			//设置特效为



         //声音
         case "sound_playuntildone":			//播放声音等待播完
         case "sound_play":				//播放声音
         case "sound_seteffectto":			//将音效设为
         case "sound_changevolumeby":			//将音量增加
         case "sound_setvolumeto":			//将音量设为
         case "sound_volume":				//音量
         case "sound_stopallsounds":			//停止所有声音
         case "sound_cleareffects":			//清除音效
         case "sound_changeeffectby":


         //事件
         case "event_broadcast":			//广播消息
         case "event_broadcastandwait":			//广播消息

         //控制
         case "control_wait":				//等待
         /////case "control_repeat":			//重复
         case "control_delete_this_clone":		//删除此克隆体
         case "control_create_clone_of";
         case "control_stop":

         //侦测
         case "sensing_distanceto":			//到目标的距离
         case "sensing_touchingcolor":			//碰到颜色
         case "sensing_coloristouchingcolor":		//颜色碰到颜色
         case "sensing_resettimer":			//计时器归零
         case "sensing_mousedown":			//鼠标是否按下
         case "sensing_keypressed":
         case "sensing_timer":
         case "sensing_touchingobject":
         case "sensing_askandwait":
         case "sensing_answer":



         //运算
         case "operator_random":
         case "operator_contains":
         case "operator_join":
         case "operator_length":
         case "operator_round":
         case "operator_mod":
         case "operator_add":
         case "operator_subtract":
         case "operator_divide":
         case "operator_multiply":
         case "operator_length":
         case "operator_mathop":

         //自制积木

         //画笔
         case "pen_setPenColorToColor":			//设置画笔颜色为
         case "pen_changePenColorParamBy":		//修改画笔参数
         case "pen_setPenColorParamTo":
         case "pen_setPenSizeTo":    			//将笔的粗细设为
         case "pen_changePenSizeBy":			//将比的粗细增加
         case "pen_stamp":				//图章
         case "pen_penDown":				//落笔
         case "pen_penUp":				//抬笔
         case "pen_clear":				//全部擦除


         //音乐
         case "music_playDrumForBeats": 		//击打乐器n拍
         case "music_restForBeats":			//休止n拍
         case "music_playNoteForBeats":			//演奏音符n拍
         case "music_setInstrument":			//将乐器设为
         case "music_setTempo":				//将演奏速度设定为
         case "music_changeTempo":			//将演奏速度增加
         case "music_menu_DRUM":			//乐器列表
         case "music_getTempo":				//将演奏速度设定为

         //变量
         case "data_setvariableto":			//设置变量值为
         case "data_changevariableby":			//修改变量值为
         case "data_hidevariable":
         case "data_showvariable":			//显示变量
         case "data_addtolist":
         case "data_itemoflist":
         case "data_deleteoflist":
         case "data_itemnumoflist":
         case "data_deletealloflist":
         case "data_insertatlist":
         case "data_lengthoflist":
         case "data_replaceitemoflist":
         case "data_showlist":
         case "data_hidelist":
         case "data_listcontainsitem":



         //自制扩展
         case "chattingroom_sendReport":		//上报信息

            //对积木的参数进行处理

            /*****************************************************************************

                每个标准积木，都可以带若干个参数。参数类型如下：
                    1.fields（下拉菜单，统计下来，当前状态下，最多为1个）
                    2.inputs（输入框，可以有多个）
                每个积木转换后的函数，都形如：
                    函数名(参数1,参数2,参数3);

                自制积木的函数名比较特殊：
                    函数名_参数1__参数2__参数3_(参数1,参数2,参数3);
                为了能还原出正确的opcode，所以需要在定义中也做适当的标记：
                    void 函数名_参数1__参数2__参数3_(VAR 参数1,VAR 参数2,BOOL 参数3);
                
                规定：
                    当fields有数据时，函数的前$nFIELDS个都为fields所有；
                    其余的归inputs所有。

            *****************************************************************************/


            /*****************************************************************************
                数据可能是这样的：
                    Array(
                         [0] => data_setvariableto
                         [1] => (
                         [2] => a
                         [3] => ,
                         [4] => 10+3-operator_random(1,30)
                         [5] => )
                    )

            *****************************************************************************/

            //拆分参数
            $arrArguments=Array();							//之前拆分后，由公式组成的参数，会被拆分成多个数据，需要重新拼接在一起
            $nArgumentCount=0;
            $nBraceCounter=0;
            $n=2;

//echo "？？？？？？？？？？？？？？？";
print_r($arrFuncData);
            while($n<$nFuncLength)							//获取函数的所有参数，以完整括号为拆分依据，其实存在bug，
            {										//已经在后面代码里补上了。
               if($arrFuncData[$n]=='(') $nBraceCounter++;
               else if($arrFuncData[$n]==')') $nBraceCounter--;
               if($nBraceCounter<0)  break;							//为负，则解析结束
               else if($nBraceCounter==0 && $arrFuncData[$n]==',') {$nArgumentCount++;}		//括号匹配完成，且正好是逗号，表示一个参数结束
               else
               {
                  $arrArguments[$nArgumentCount][]=$arrFuncData[$n];				//普通参数
               }
               $n++;
            }
            $n++;
            print_r($arrArguments);
echo "aaaaaaaaaaaaaaaaaaaaaaa????????? $n ???????????????????????????\n";
            /*****************************************************************************
                数据也可能是这样的：
                    Array
                    (
                        [0] => sensing_mousedown
                        [1] => (
                        [2] => )				//当到这里时，看似得到了一个完整的积木数据，但实际它只是表达式的一部分
                        [3] => &&1+2*3-4>5+6*7-8 &&		//所以还需要再判断数组是否存在当前这个偏移量。
                        [4] => (
                        [5] => 9*10-11<12-13+14 
                        [6] => )
                    )

            *****************************************************************************/
//echo "next $n ".$arrFuncData[$n]." nnnnnnnnnnnnnnnnnnnnnnn\n";


     // $n= preg_match_all("/==|>|<|!=/",$strLogicExpression,$m,PREG_OFFSET_CAPTURE);			//按最基本的大于、等于、小于进行定位
     // $nStrLength=strlen($strLogicExpression);

//if(!isset($arrFuncData[$n]) && preg_match_all("/\+|\-|\*|\/|\(|\)/",$arrFuncData[$n],$m))
//{}
//else
//var_dump();

echo $arrFuncData[$n]."nnnnnnnnnnnnnnnnnnnn $n nnn\n";
            if((!isset($arrFuncData[$n])  || $arrFuncData[$n]==";") && preg_match_all("/\+|\-|\*|\/|\(|\)/",$arrFuncData[$n],$m)==false && $this->getArgName($arrFuncData[0])!=NULL)			//检测后面是否还有数据，以确认当前获取的是完整数据，此操作补上了之前按括号拆分可能存在的bug。
            {
               $arrBlockArgConfig=$this->getArgName($arrFuncData[0]);			//获取当前积木块的fields和inputs参数的配置信息


print_r($arrBlockArgConfig);

               $nINPUTS=count($arrBlockArgConfig["inputs"]);				//inputs参数统计
               $nFIELDS=count($arrBlockArgConfig["fields"]);				//fields参数统计

               /**************************************************
                  校验解析是否正确，可以用：
            
                  $nArgumentCount==$nINPUTS+$nFIELDS

               **************************************************/

               //如果有参数，就进行处理；无参数，则忽略。

               //构建fields参数
               $strFieldsData='';
               if($nFIELDS>0)								//构建fields数据。fields不需要shadow积木。
               {										//注意：fields数据也可能是公式，需要处理。
echo "FIELDS以后再处理。";
                  //if(
                  $strFIELDS=implode("",$arrArguments[0]);

//以后再处理。
                  $listName=trim($strFIELDS);						//如果有FIELDS，那么一定要放在第一个参数位置。
											//operator_mathop比较复杂，它的FIELDS当做函数名来用了，所以需要strActuralArg进行转换。
                  $listUID=isset($this->arrVariableUIDS[$listName])?$this->arrVariableUIDS[$listName]:UID();

                  $strFieldsData.='"'.$arrBlockArgConfig["fields"][0][0].'": {"name": "'.$arrBlockArgConfig["fields"][0][0].'","id": "'.$listUID.'","value": "'.($strActuralArg!=""?$strActuralArg:trim($listName,'"')).'"'. (isset($arrBlockArgConfig["fields"][0][1])?',"variableType": "'.$arrBlockArgConfig["fields"][0][1].'"':'').'}';
               }
               if($strActuralArg!="")   $nFIELDS=0;					//abs/ceiling等函数，实际调用了operator_mathop，这些函数名是mathop的field的value，所以实际参数要从0开始，不能受nFIELDS干扰。

               //构建inputs参数
               $strInputsData='';
               if($nINPUTS>0)								//构建inputs数据
               {
                  //拼接当前积木的参数

                  //拆分参数
                  /***************************************************************************************
                    单个参数可能是表达式（例如：1+3*4/我的变量），
                    经过前期数据处理后，会被分解成多个数据，但参数间还是用“,”来分隔的，
                    所以要按“,”来识别和拆分参数。

                  ***************************************************************************************/
                  //echo "检查有没有被完全拆开:\n";
               //此时实际还没有IDDI参与进来。

echo "拆分后的参数数据：\n";
print_r($arrArguments);

                  //对每个参数进行细分。参数可能是纯数字、字符串、变量和计算表达式
                  $parsedArgData=Array();
                  //当$arrArguments为空时，$nArgumentCount也为0，for还是会执行，所以需要判断实际获取到的参数的个数。

                  if(count($arrArguments[0])>0)
                  {
                     for($i=0;$i<=$nArgumentCount;$i++)				//函数有多个参数，对每个参数分别进行解析处理
                     {

//echo "forrrrrrrrrrrrrrrrrrrrrr\n i: $i fields: $nFIELDS\n";
print_r($arrArguments);
                        if(!isset($arrArguments[$i+$nFIELDS])) break;

                        $strArgument=trim(implode("",$arrArguments[$i+$nFIELDS]));         //这里有问题。
echo  "\n\n".$i ."参数是 ".$strArgument."\n";

                        echo "解析前的参数 $i :".$strArgument."\n";
                        if(!is_numeric($strArgument))					//非纯数字的参数，利用RPN算法进行分解。
                        {
                           if(isset($this->arrVariableUIDS[trim($strArgument)]))//in_array(trim($arrLoopCondition),$this->arrVariables) )	//参数是已定义的变量，生成该变量的积木块，此处不需要shadow，shadow由repeat自己生成。
                           {
                              $parsedArgData[$i]=trim($strArgument);
echo "是变量。"; 
                           }
                           else if(isset($this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock][trim($strArgument)]))
                           {
                              $parsedArgData[$i]=trim($strArgument);
                           }
                           else if($strArgument[0]!='"')				//无双引号，非字符串
                           {
                              $arg=NULL;
                              if($this->rpn_calc-> init($strArgument))			//将四则混合运算字符串交由RPN来完成解析
                                 $arg=$this->rpn_calc->toScratchJSON();			//生成符合Scratch3.0要求的数组数据
                              if($arg==NULL) $parsedArgData[$i]=$strArgument;		//如因括号不匹配之类的问题导致解析失败，则直接使用，因为可能是关键词。
                              else
                              {
                                 $parsedArgData[$i]=$arg;				//解析成功，返回经RPN解析后的四则不混合运算数据
                              }
//echo ",,,,,,,,,,,,,,,,,,,\n";
//print_r($parsedArgData);
                           }
                           else								//字符串文本
                           {
                              $parsedArgData[$i]=trim($strArgument);
                           }
                        }
                        else $parsedArgData[$i]=trim($strArgument);			//纯数字参数，注意去除空格。


                        $strShadowUID=UID();

                        //生成参数积木，返回UID 
echo "准备生成的数据1:";
print_r($parsedArgData);
//这里强制0了？？？？
                        $arrChildUID=$this->parseCalculationExpression($arrBlockArgConfig["inputs"][$i],$parsedArgData[$i],$thisUID); //解析的过程中，也会创建相应的积木数据，最终返回UID
echo "返回的数据:\n";
print_r($arrChildUID);
                        //只要是文本或数字的参数，都要有Shadow，这个shadow不在parseCalculationExpression里创建。
                        if($arrChildUID[1]!=NULL)
                        {
                           //VAR类型参数需要补一个shadow，BOOL类型不需要。
                           array_push($this->Blockly,'{"S":"0","id": "'.$strShadowUID.'","opcode": "'.$arrBlockArgConfig["inputs"][$i][1].'","inputs": {},"fields": {"'.$arrBlockArgConfig["inputs"][$i][2].'": {"name": "'.$arrBlockArgConfig["inputs"][$i][2].'","value": "10"  }},"next": null,"topLevel":true,"parent":null,"shadow":true}');//"topLevel": '.($thisUID==NULL?'true':'false').',"parent":'.($thisUID==NULL?'null':'"'.$thisUID.'"').',"shadow": true}');
                           //拼接主积木的参数数据
                           $strInputsData.=($i>0?',':'') . ' "'.$arrBlockArgConfig["inputs"][$i][0].'": {"TAG":"1", "name": "'.$arrBlockArgConfig["inputs"][$i][0].'", "block": "'.$arrChildUID[0].'", "shadow": "'.$strShadowUID.'"  }';
                        }
                        else
                        {
                           //拼接主积木的参数数据
                           $strInputsData.=($i>0?',':'') . ' "'.$arrBlockArgConfig["inputs"][$i][0].'": {"TAG":"2",  "name": "'.$arrBlockArgConfig["inputs"][$i][0].'", "block": "'.$arrChildUID[0].'", "shadow": "'.$arrChildUID[0].'" }';
                        }
                     }
                  }
                  /*******************************************************************

                     示例：
                     motion_gotoxy( 10+1+(2-3*4-2)+operator_random(1,100), 10+1+2-3*4-2+operator_random(1,100) );

                     参数解析后：
                     Array
                     (
                         [0] => Array
                             (
                               [0] => 10+1+
                               [1] => (
                               [2] => 2-3*4-2
                               [3] => )
                               [4] => +operator_random
                               [5] => (
                               [6] => 1
                               [7] => ,
                               [8] => 100
                               [9] => )
                             )
                         [1] => Array
                             (
                               [0] => 10+1+2-3*4-2+operator_random
                               [1] => (
                               [2] => 1
                               [3] => ,
                               [4] => 100
                               [5] => )
                             )
                     )

                  *******************************************************************/
//echo "+++++++++++++++++\nparsedArgData\n";
                  print_r($parsedArgData);
//print_r($arrBlockArgConfig["inputs"]);

               }


//echo " parent: $parentUID    this: $thisUID  next: $nextUID\n";
               //拼接主积木完整数据
               $strBlock='{"main":"0","id": "'.$thisUID.'","opcode": "'.$arrFuncData[0].'","fields":{'.$strFieldsData.'}, "inputs": {'.$strInputsData.'}, "next": '.($nextUID==NULL?'null':'"'.$nextUID.'"').', "topLevel": '.($parentUID==NULL?'true':'false').', "parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').', "shadow": false}';	//当bTOPLEVEL为true时，topLevel必为true；否则按元规则处理。
																		//当bTOPLEVEL为true时，parent必为null；否则按原规则处理。	//这个添加的规则，可以让单独的积木能正确显示。

//echo "-00000000000-------PARENTUID------- $parentUID --------- $arrFuncData[0] ------------2---------\n";
//var_dump($strFieldsData);
//echo $strBlock."\n\n";
            

            
//echo $strBlock."\n";
               //添加当前积木块数据
               array_push($this->Blockly,$strBlock);


               break;
            }
            //else{} //前面获取的不是完整的数据，所以需要由default来处理。

         default:					//其他特例

//echo "default\n";
            /*******************************************************************************************************

              自制积木调用

              自制积木，在类C文本代码中，对应的是自定义函数。由于自制积木不可返回数值，所以自定义函数类型为“void”。

              自定义函数可以有两种类型的参数：1.VAR 数字和文本， 2.BOOL 布尔值
              每个自定义函数，为便于还原其参数等信息，需要把变量名追加到函数名后相应的位置。
              例如：
                  自制积木名字叫“如果[a]就显示[b]，否则显示[c]”，有一个BOOL参数a，两个VAR 参数b和c，一个BOOL参数b，则该函数的定义为：
                      void 如果_a_就显示_b_否则显示_c_(BOOL a, VAR b, VAR c)
                      {
                          //实现代码
                      }

                  对其进行调用时，需要使用如下命令：
                      如果_a_就显示_b_否则显示_c_(1>2,1,2);

                  也支持这样的布局：
                      如果_a_就显示_b_否则显示_c_
                      (1>2,1,2);

                  但不支持函数名与括号间有>1个回车符号：
                      如果_a_就显示_b_否则显示_c_

                      (1>2,1,2);       // 这其实是个数据拆分时的BUG，但不去处理了。 


                  自制积木的文本代码在解析的时候，会把所有参数的类型配置信息，保存在：
                      $this->arrSelfDefinedFunctions["如果_a_就显示_b_否则显示_c_"]=> 	//在名字中嵌入变量名，是为了方便定位变量控件的显示位置
                        {
                          [0]=> "如果 %b 就显示 %s 否则显示 %s"				//Blocky积木里，用%b表示BOOL，%s表示VAR。
                          [1]=> {							//保存变量的类型
                            [0]=> "BOOL"
                            [1]=> "VAR"
                            [2]=> "VAR"
                          }
                          [2]=> {							//保存变量的名字
                            [0]=> "a"
                            [1]=> "b"
                            [2]=> "c"
                          }
                        }

              
                  转换成积木后，会显示为：
                      如果<1>2>就显示(1)否则显示(2)

            *******************************************************************************************************/

            $strFunctionName=str_replace(" ","",$arrFuncData[0]);

            if(isset($this->arrSelfDefinedFunctions[$strFunctionName]))		//确认当前函数名是否在自制积木列表中。
            {
               /****************************************************************************************************
                  类C代码：
                      如果_a_就显示_b_否则显示_c_(1>2||((2+3)-sin(4))*5/3<0 || sensing_colorintouchcolor("#121121","#362514"),1,2);

                  拆分后：
                      array(24) {
                          [0]=> "如果_a_就显示_b_否则显示_c_"
                          [1]=> "("
                          [2]=> "1>2||"
                          [3]=> "("
                          [4]=> "("
                          [5]=> "2+3"
                          [6]=> ")"
                          [7]=> "-sin"
                          [8]=> "("
                          [9]=> "4"
                          [10]=> ")"
                          [11]=> ")"
                          [12]=> "*5/3<0 || sensing_colorintouchcolor"
                          [13]=> "("
                          [14]=> ""#121121""
                          [15]=> ","
                          [16]=> ""#362514""
                          [17]=> ")"
                          [18]=> ","
                          [19]=> "1"
                          [20]=> ","
                          [21]=> "2"
                          [22]=> ")"
                          [23]=> ";"
                       }

                   参数拆分：
                       array(3) {
                          [0]=> "1>2||((2+3)-sin(4))*5/3<0 || sensing_colorintouchcolor("#121121","#362514")"
                          [1]=> "1"
                          [2]=> "2"
                       }

               ****************************************************************************************************/

echo "SDFFFFFFFFFFFFFFF ".$this->arrCurrentSDFBlock." CHANGED\n";
               //$this->arrCurrentSDFBlock=$strFunctionName;					//当前积木的名字，形如：函数名_变量名1__变量名2_
											//当arrCurrentSDFBlock非空，表示当前可以用该自制积木里的参数变量，
											//这种情况一般出现在自定义函数的实现代码中。
               $strProcCode=$this->arrSelfDefinedFunctions[$strFunctionName][0];	//proccode
               $arrArgTypes = $this->arrSelfDefinedFunctions[$strFunctionName][1];	//变量的类型
               $arrArgNames = $this->arrSelfDefinedFunctions[$strFunctionName][2];	//变量的名字
               $arrArgUIDS  = $this->arrSelfDefinedFunctionArgs[$strFunctionName];		//变量的UID
               //var_dump($arrArgNames);

               /****************************************************************************************************
               1. $this->arrSelfDefinedFunctions

             某个字符串，只要在$this->arrSelfDefinedFunctions里能找到，就说明它是自制积木。

             数据格式：
                Array
                (
                    [func2_a__b_] => Array		//函数名，其中嵌入了参数，每个参数名前后都加一个“_”以示区分。
                        (
                            [0] => func2 %s %b		//proccode，在procedures_call里用来识别自制积木。%s表示数字或文本，%b表示布尔值。
                            [1] => Array		//参数类型
                                (
                                    [0] => VAR		//数字或文本
                                    [1] => BOOL		//布尔值：true/false
                                )
                            [2] => Array		//参数名
                                (
                                    [0] => a
                                    [1] => b
                                )
                        )
                )

               2. $this->arrSelfDefinedFunctionArgs

             与变量一样，自制积木的参数变量，在整个项目中，都是唯一的，每个参数变量的UID，都可以通过函数名和变量名两个参数来查询。

             数据格式：
                Array
                (
                    [func1_a_] => Array				//key为自定义函数名
                        (
                            [a] => ID_diJT50o#76Wb]bb~dHvL_DI	//key为参数变量名，值为该参数的UID
                        )
                    [func2_a__b_] => Array
                        (
                            [a] => ID_{bmH46dyG17a:`?f9lfi_DI
                            [b] => ID_baOs9[LMnu#Iau5irS@D_DI
                        )
                )
               ****************************************************************************************************/


               //拼接当前积木的参数
               $arrArgumentsData=Array();						//之前拆分后，由公式组成的参数，会被拆分成多个数据，需要重新拼接在一起
               $nArgumentLength=0;							//参数数量
               $nBraceCounter=0;							//小括号（）计数器
               $nOffset=2;								//直接去掉2个数据   偏移量 0：自定义函数名 1：“(”

               while($nOffset<$nFuncLength-2)				//参数拆分	//末尾也直接去掉两个：“)”和“;”。
               {
                   $chCH=$arrFuncData[$nOffset++];					//从偏移量2开始遍历取值

                   if($chCH=='(') $nBraceCounter++;					//遇到(
                   else if($chCH==')') $nBraceCounter--;				//遇到)

                   if($nBraceCounter==0 && $chCH==',') { $nArgumentLength++; }	//只看括号匹配是否结束，以及是否遇到“,”
                   else if (isset($arrArgumentsData[$nArgumentLength]))
                       $arrArgumentsData[$nArgumentLength].=$chCH;
                   else
                       $arrArgumentsData[$nArgumentLength]=$chCH;
               }

               if(1){
                  echo "////////////////////////// arrArgumentsData ///////////////////////////\n";
                  var_dump($arrArgumentsData);
                  echo "////////////////////////// arrArgumentsData ///////////////////////////\n";
               }

               /*******************************************************************************************
                  自制积木的变量，在定义时，就生成了固定的UID，且在argumentuids里标明了先后顺序，
                  在调用时，也需要使用这些已经生成的固定的UID。
                  在定义时，还设定了默认值，记录在argumentdefaults里。
                     例如：
                     void f_a__b__c_( VAR a , BOOL b , VAR c ){}

                     实际生成的定义数据中，对变量有如下设定：
                     "inputs":{
                         "o/NZni@*?9Q`vIuVZeB;": {					//$arrArgUIDS[参数名]
                             "name": "o/NZni@*?9Q`vIuVZeB;",				//$arrArgUIDS[参数名]
                             "block": "==h,r_p3DB`U]o99t3j(",				//UID();实际参数值
                             "shadow": "==h,r_p3DB`U]o99t3j("				//UID();默认参数值 如为BOOL类型，则无默认参数值，为null；如实际参数值为常量，则block与shadow相同。
                         },
                         "7]LI!]j_vi+vR%b|CDwX": {
                             "name": "7]LI!]j_vi+vR%b|CDwX",
                             "block": "5=,zElY?jB{.e4`5ln}h",
                             "shadow": "5=,zElY?jB{.e4`5ln}h"
                         },
                         "v_eQ0D.?e!8~K1Ys)n+p": {
                             "name": "v_eQ0D.?e!8~K1Ys)n+p",
                             "block": "{MTp3soNL}*7|!BM3n-%",
                             "shadow": "{MTp3soNL}*7|!BM3n-%"
                         }
                     },
                     "mutation":{
                         "tagName": "mutation",
                         "children": [],
                         "proccode": "f %s %b %s",									//已有：$strProcCode
                         "argumentids": "[\"o/NZni@*?9Q`vIuVZeB;\",\"7]LI!]j_vi+vR%b|CDwX\",\"v_eQ0D.?e!8~K1Ys)n+p\"]",	//已有：$arrArgUIDS
                         "argumentnames": "[\"a\",\"b\",\"c\"]",							//已有：$arrArgNames
                         "argumentdefaults": "[\"\",\"false\",\"\"]",							//需要构造
                         "warp": "false"
                     }

                   在后续使用这些变量时，需要用到相对应的UID。
                   在调用该积木时，也需要提供proccode和argumentids，前者用于识别积木，后者用于确定变量。
               *******************************************************************************************/

               $input_str="";				//拼接inputs
               $argumentids_str="[";			//拼接argumentids

               //$arrArgUID=Array();
               //if(isset($this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock]))			//获取当前自制积木的参数信息
               //   $arrArgUID=$this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock];	//去除所有的文本index，以数字为索引。

//echo "CURRENT SDFBlock arg:\n";
               //var_dump($arrArgUID);								//这个方法可能会乱序，所以取消了。

               /*******************************************************************************************
                   生成参数积木

                   参数类型共有两种：
                       VAR  可以是纯数字、字符串、变量、自制积木的参数、算术表达式或返回数值的函数的调用；
                       BOOL 可以是逻辑表达式或判断函数的调用。

               *******************************************************************************************/
               //$argArr=Array();
echo "SDF ARGUMENTS\n";
print_r($arrArgumentsData);
print_r($arrArgTypes);

print_r($this->arrSelfDefinedFunctionArgs);
echo $this->arrCurrentSDFBlock;



               for($i=0;$i<=$nArgumentLength;$i++)				//遍历所有参数
               {
                  $arrChildUID=NULL;

                  if($i>0)							//多个参数拼接，需要加逗号。
                  {
                     $input_str.=","; 
                     $argumentids_str.=",";
                  }

                  $strArg=trim($arrArgumentsData[$i],'"');

                  if($arrArgTypes[$i]=="VAR")					//参数类型为VAR，文本或数字或算术表达式的处理
                  {
                     if(is_numeric($arrArgumentsData[$i]))			//纯数字的参数，直接使用。
                     {
                        $parsedVARData=$arrArgumentsData[$i];
                        $arrChildUID[0]=UID();
                        array_push($this->Blockly, '{"a":"4","id": "'.$arrChildUID[0].'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.$parsedVARData.'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                     }
                     else if(isset($this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock][$strArg]))  //[$arrArgumentsData[$i]]))			//获取当前自制积木的参数信息
                     {
                         $arrChildUID[0]=UID();
                         array_push($this->Blockly, '{"a":"1","id": "'.$arrChildUID[0].'","opcode":"argument_reporter_string_number","inputs": {},"fields": {"VALUE": {"name": "VALUE","value": "'.$strArg.'"}},"next": null,"topLevel": false,"parent":  "'.$thisUID.'","shadow":false}');
                     }
                     else 							//非纯数字参数，利用RPN算法进行拆分。
                     {
                        $arrRPNResult=NULL;

                        if($this->rpn_calc -> init($arrArgumentsData[$i]))	//将字符串交由RPN来完成解析，可以处理算术表达式，可识别计算表达式、函数调用、变量和参数等。
                        $arrRPNResult=$this->rpn_calc->toScratchJSON();

                        if($arrRPNResult==NULL) $parsedVARData=$arrArgumentsData[$i];	//解析失败，可能是普通文本，也可能因括号不匹配之类的问题导致解析失败，总之直接使用。
                        else
                        {
                           $parsedVARData=$arrRPNResult;				//解析成功，返回经RPN解析后的四则不混合运算数据
                        }

                        $arrChildUID=$this->parseCalculationExpression(Array('TEXT','text','TEXT'),$parsedVARData,$thisUID); 
                     }

                     /*******************************************************************************************

                        先经$this->rpn_calc->init()拆分，再用toScratchJSON()解析后，将得到如下数据：
                        例如：
                             1+3/sin(0.1)*3-我的变量

                        结果：
                           Array
                           (
                               [0] => Array
                                   (
                                       [0] => sin				//计算符号+-* /，或函数名称sin,cos,abs,round...
                                       [1] => ID_74{#aHHPCatlJ)iKYkz{_DI	//该积木块的UID
                                       [2] => 0.1				//参数1
                                       [3] => 0					//参数2
                                   )
                               [1] => Array
                                   (
                                       [0] => *
                                       [1] => ID_}5tydp#~vJD0L,I|O@BO_DI
                                       [2] => ID_74{#aHHPCatlJ}iKYkz{_DI	//参数可以是另一块积木的UID
                                       [3] => 3
                                   )
                               [2] => Array
                                   (
                                       [0] => -
                                       [1] => ID_4Og[}Y~Hs?|.J{//{XM6_DI
                                       [2] => ID_}5tydp#~vJD0L,I|O@BO_DI
                                       [3] => 我的变量				//如果是可用的变量，先直接显示变量名
                                   )
                               [3] => Array
                                   (
                                       [0] => /
                                       [1] => ID_Q-;`KOK2@%hvpoq={nHd_DI
                                       [2] => 3
                                       [3] => ID_4Og[}Y~Hs?|.J{//{XM6_DI
                                   )
                               [4] => Array
                                   (
                                       [0] => +
                                       [1] => ID_hPs.{[lt::PE`{XU]}Ra_DI	//最后一组数据的UID是所有算术表达式中最底部的那块积木，这个UID需要传递给调用者。
                                       [2] => 1					//所以数据的生成，需要逆推。好像也不是必须的？？？
                                       [3] => ID_Q-;`KOK2@%hvpoq={nHd_DI
                                   )
                           )

                     *******************************************************************************************/

                     //生成参数积木，返回UID
                     //Array('TEXT','text','TEXT')是一组给+-*和/使用的通用数据模板
                     //其他函数的调用，会按照函数自己的定义来确定参数类型

                     //VAR参数要有缺省值shadow，这个shadow并没有在parseCalculationExpression里创建，所以需要在这里补一个。
                     $strShadowUID=UID();

                     if($arrChildUID[1]!=NULL)					//表示存在shadow，需要补一个。这个待议，代码调整过很多次了，实际是否需要如此处理，等整理了parseCalculationExpression再说。
                     {
                        //如果是BOOL类型，就不需要补这个shadow
                        array_push($this->Blockly,    '{"a":"3","id": "'.$strShadowUID.'","opcode": "text","inputs": {},"fields": {    "TEXT": {"name": "TEXT","value": "10"    }},"next": null,"topLevel": true,"parent": null,"shadow": true}');
                     }
                     else							//shadow已生成，不需要再生成shadow。??????待检测。
                     {
                        //拼接主积木的参数数据
                        $strShadowUID=$arrChildUID[0];
                     }

                     $input_str	.='"'.$arrArgUIDS[$arrArgNames[$i]].'": { "name": "'.$arrArgUIDS[$arrArgNames[$i]].'", "block": "'.$arrChildUID[0].'", "shadow": "'.$strShadowUID.'"}'; //name的UID也必须与定义时的数据保持一致。如果参数是布尔值，shadow为null

                     //echo "\nVARRRRRRRRRRRRRRRR\t\t\t\t".$input_str."\t\t\tVARRRRRRRRRRRRRR\n";

                     $argumentids_str	.='\"'.$arrArgUIDS[$arrArgNames[$i]].'\"';  		//调用时这里的UID应该是定义时的UID，这样才能把参数传递过去。

                  }
                  else			//参数类型为BOOL，逻辑表达式或逻辑判断函数的处理
                  {


                     //if(is_numeric($strArg))			//布尔值不会有纯数字的参数输入。
                     //{
                     //   $parsedVARData=$strArg;
                     //}
                     //else 
                     if(isset($this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock][$strArg]))			//获取当前自制积木的参数信息
                     {
                         $arrChildUID[0]=UID();
                         array_push($this->Blockly, '{"a":"1","id": "'.$arrChildUID[0].'","opcode":"argument_reporter_boolean","inputs": {},"fields": {"VALUE": {"name": "VALUE","value": "'.$strArg.'"}},"next": null,"topLevel": false,"parent":  "'.$thisUID.'","shadow":false}');
                     }
                     else
                     {
                        $parsedVARData=$this->rpn_logic->init($arrArgumentsData[$i]);
                        if($parsedVARData!=NULL)
                        {
                           $arrChildUID=Array(NULL,NULL);//实际只要一个，暂时这样，保证数据结构的完整性。
                           if(!empty($parsedVARData[0])||!empty($parsedVARData[1]) || !empty($parsedVARData[2]))	//全为空，则直接创建
                           {
                              $arrChildUID=$this->parseLogicExpression($parsedVARData,$thisUID);	//头部积木UID要用。
                           }
                        }
                        //else{}   //当前是布尔值变量，所以如果rpn_logic解析失败，就没有必要去检测算术表达式了。
                     }
                     $input_str	.='"'.$arrArgUIDS[$arrArgNames[$i]].'": { "name": "'.$arrArgUIDS[$arrArgNames[$i]].'", "block": "'.$arrChildUID[0].'", "shadow": null}';	//name和ID也必须为定义时使用的UID。布尔值参数不设置shadow.
                     $argumentids_str	.='\"'.$arrArgUIDS[$arrArgNames[$i]].'\"';  //调用时这里的UID应该是定义时的UID，这样才能把参数传递过去。
                  }
               }

               $argumentids_str.="]";

               /********************************************************
                  procedures_call里的mutation，只需要：
                     argumentids
                     proccode
               ********************************************************/
               if($thisUID=='null')
               {
                  array_push($this->Blockly,'{"id": "'.UID().'","opcode": "procedures_call","inputs": {'.$input_str.'},"fields": {},"next":  '.($nextUID==NULL?'null':"\"".$nextUID."\"").',"topLevel": true,"parent": null,"shadow": false,"mutation": {    "tagName": "mutation",    "children": [],    "proccode": "'.$this->arrSelfDefinedFunctions[$strFunctionName][0].'",    "argumentids": "'.$argumentids_str.'",    "warp": "false"}    }');
               }
               else
               {
                  array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "procedures_call","inputs": {'.$input_str.'},"fields": {},"next":  '.($nextUID==NULL?'null':"\"".$nextUID."\"").',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').',"shadow": false,"mutation": {    "tagName": "mutation",    "children": [],    "proccode": "'.$this->arrSelfDefinedFunctions[$strFunctionName][0].'",    "argumentids": "'.$argumentids_str.'",    "warp": "false"}    }');
                  //argumentids 					//这里ID要跟prototype的保持一致
               }
               return NULL;
            }//调用自制积木的处理结束。


            //对变量进行赋值操作的处理
            //如果变量未定义，则在代码执行后，会自动添加该变量，且该变量的类型为适用于所有角色。
            $arrData=explode("=",$arrFuncData[0]);

            if(count($arrData)==2)				//有赋值操作
            {
               //echo "++++++++++++++++++++++++++ $arrFuncData[0] ==========================\n";
               //var_dump($arrData);
               //var_dump($arrFuncData);

               $strCalcArg=Array(trim($arrData[0]),trim($arrData[1]));//置默认初始值为拆分后的数据

               if($nFuncLength>2)					//如果传递进来的数组大于2，则表示赋值表达式被拆分成多行了。
               {
                  for($r=1;$r<$nFuncLength-1;$r++)			//掐头（变量名+=）去尾(;)
                     $strCalcArg[1].=$arrFuncData[$r];
               }

               //if($strCalcArg[0][strlen($strCalcArg[0])-1]=="+")	//判断是否是+=
               if(isset($strCalcArg[0][-1]) && $strCalcArg[0][-1]=="+")	//PHP >=7.1
               {
                  array_pop($this->UIDS);
                  array_pop($this->UIDS);
									//如果要调用parseGeneralBlocks()，则需要往$this->UIDS内压入两个UID，
                  array_push($this->UIDS,$thisUID);			//后压入的作为parentUID。				//当前这一轮的thisUID已经被取出，但实际要到下一次调用时才使用，所以仍旧压回去。
                  $this->parseGeneralBlocks(Array("data_changevariableby","(", trim($strCalcArg[0],'+'),",", $strCalcArg[1],")"));
               }
               else							//否则是=
               {
                  array_pop($this->UIDS);
                  array_pop($this->UIDS);
									//如果要调用parseGeneralBlocks()，则需要往$this->UIDS内压入两个UID，
                  array_push($this->UIDS,$thisUID);			//后压入的作为parentUID。

                  $this->parseGeneralBlocks(Array("data_setvariableto","(",$strCalcArg[0],",", $strCalcArg[1],")"));//Array(opcode,arg1,arg2)
               }
            }
            else
            {

               $strData=implode($arrFuncData);
               $this->bTOPLEVEL=true;

               $parsedBOOLData=$this->rpn_logic->init($strData);	//先判断是不是逻辑运算表达式
               if(!empty($parsedBOOLData[0]))				//算法已改进，如果[0]无数据，则[1]的数据将保存到[0]中。	
               {							//所以只要[0]为空，就表示该字符串无逻辑运算操作。
                  $this->parseLogicExpression($parsedBOOLData,NULL);	//处理逻辑表达式
               }
               else if(!empty($parsedBOOLData[2]))			//处理算术表达式。充分利用逻辑表达式处理后的数据
               {
                  if($parsedBOOLData[3]!=NULL)				//处理后的字符串有数据，则有两种情况
                  {
                     /**************************************************************
                        适用于带函数的算术表达式
                        例如： random(3,5)+random(3,2)
                        解析结果为：
                           Array
                           (
                               [0] => Array()				//[0]空，也就意味着[1]也空，那么当前数据就是非逻辑表达式
                               [2] => Array				//函数调用
                                   (
                                       [0] => Array
                                           (
                                               [0] => random
                                               [1] => ID_N;]FqfUHrlBJNYItC4Kb_DI
                                               [2] => 3,2
                                           )
                                       [1] => Array
                                           (
                                               [0] => random
                                               [1] => ID_:Qh$nMyX[z;v#:.f7t;v_DI
                                               [2] => 3,5
                                           )
                                   )
                               [3] => ID_:Qh$nMyX[z;v#:.f7t;v_DI + ID_N;]FqfUHrlBJNYItC4Kb_DI	//表达式经预处理后最终的形式
                           )

                     **************************************************************/
                     $arrLoopCondition=$this->rpn_calc->init($parsedBOOLData[3]);

                     if($arrLoopCondition===TRUE)			//一种是[3]字符串可以继续拆分		//拆分成功
                     {
                        $arrLoopCondition=$this->rpn_calc->toScratchJSON();
                        $arrLoopCondition[1]=$parsedBOOLData[2];	//前面已经处理过函数调用了，所以这里不会再有函数调用的数据，需要补上。
                        $arrChildUIDX=$this->parseCalculationExpression(Array("NUM","math_number","NUM"),$arrLoopCondition,NULL);
                     }
                     else						//一种是[3]不可再拆分
                     {
                        /*****************************************************************************************
                           适用于单独函数嵌套调用的算术表达式。

                           例如：random(random(3,4),5)

                           解析结果为：
                              Array
                              (
                                  [0] => Array()
                                  [2] => Array
                                      (
                                          [0] => Array
                                              (
                                                  [0] => random
                                                  [1] => ID_9sTqJ#ULBH4_K.b:kO7G_DI
                                                  [2] => 3,4
                                              )

                                          [1] => Array
                                              (
                                                  [0] => random
                                                  [1] => ID_gp:iwNvMH6XA#hD3uA5[_DI
                                                  [2] => ID_9sTqJ#ULBH4_K.b:kO7G_DI ,5
                                              )
                                      )
                                  [3] => ID_gp:iwNvMH6XA#hD3uA5[_DI
                              )

                        *****************************************************************************************/
                        if(strlen($parsedBOOLData[3])==26)		//不可再拆分的话，这个[3]应该只剩下一个IDDI了。
                        {						//最好用preg_match，但，就简单一点吧，其实可以不判断了。
                           $arrCalcResult=Array(Array(),Array(),Array());
                           $arrCalcResult[1]=$parsedBOOLData[2];		//parseCalculationExpression只处理[0]和[1]里的数据。
                           $arrChildUIDX=$this->parseCalculationExpression(Array("NUM","math_number","NUM"),$arrCalcResult,NULL);
                        }
                     }
                  }
                  else							//只剩下函数调用了。
                  {
                     $arrCalcResult=Array(Array(),Array(),Array());
                     $arrCalcResult[1]=$parsedBOOLData[2];		//parseCalculationExpression只处理[0]和[1]里的数据。
                     $arrChildUIDX=$this->parseCalculationExpression(Array("NUM","math_number","NUM"),$arrCalcResult,NULL);
                  }
               }
               else if($parsedBOOLData[3]!=NULL)
               {
                  /*****************************************************************************************
                      适用于无函数调用且无小括号的算术表达式。

                          例如：1+2*3/5

                          解析结果为：
                              Array
                              (
                                  [0] => Array()
                                  [2] => Array()
                                  [3] => 1+2*3/5
                              )

                  *****************************************************************************************/

                  $arrLoopCondition=$this->rpn_calc->init($parsedBOOLData[3]);

                  if($arrLoopCondition===TRUE)					//拆分成功
                  {
                     $arrLoopCondition=$this->rpn_calc->toScratchJSON();

                     $arrLoopCondition[1]=$parsedBOOLData[2];	//parseCalculationExpression只处理[0]和[1]里的数据。
                     $arrChildUIDX=$this->parseCalculationExpression(Array("NUM","math_number","NUM"),$arrLoopCondition,NULL);//$arrLoopCondition,NULL);
                  }
                  else
                  {
                     if(strlen($parsedBOOLData[3])==26)	//表达式只剩下一个IDDI了。
                     {
                        $arrCalcResult=Array(Array(),Array(),Array());
                        $arrCalcResult[1]=$parsedBOOLData[2];		//parseCalculationExpression只处理[0]和[1]里的数据。
                        $arrChildUIDX=$this->parseCalculationExpression(Array("NUM","math_number","NUM"),$arrCalcResult,NULL);//$arrLoopCondition,NULL);
                     }
                  }
               }




/*


               $arrData=explode(".",$arrFuncData[0]);		//C++的成员函数访问表达解析，已经废弃。
               if(count($arrData)==2)
               {
                  switch($arrData[1])
                  {
                     case "push":	//将东西加入
                        $argChildUID=UID();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_addtolist","inputs": {"ITEM": {"name": "ITEM","block": "'.$argChildUID.'","shadow": "'.$argChildUID.'"}},"fields": {"LIST": {"name": "LIST","id": "'.UID().'","value": "'.trim($arrData[0]).'","variableType": "list"}}, "next": '.($nextUID!=NULL?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').',"shadow": false}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID.'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.trim($arrFuncData[2],'"').'"}},"next": null,"topLevel": false,   "parent": "'.$thisUID.'","shadow": true}');

                     break;
                     case "delete":	//删除第n项
                        $argChildUID=UID();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_deleteoflist","inputs": {"INDEX": {"name": "INDEX","block": "'.$argChildUID.'","shadow": "'.$argChildUID.'"}},"fields": {"LIST": {"name": "LIST","id": "'.UID().'","value": "'.trim($arrData[0]).'","variableType": "list"}}, "next": '.($nextUID!=NULL?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==null?'null':'"'.$parentUID.'"').',"shadow": false}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID.'","opcode": "math_integer","inputs": {},"fields": {"NUM": {"name": "NUM","value": "'.trim($arrFuncData[2],'"').'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        
                     break;
                     case "removeAll":	//删除全部项目
                        $argChildUID=UID();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_deletealloflist","inputs": {},"fields": {"LIST": {"name": "LIST","id": "'.UID().'","value": "'.trim($arrData[0]).'","variableType": "list"}}, "next": '.($nextUID!=NULL?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').',"shadow": false}');
                        
                     break;
                     case "insert":	//在第n项前插入
                        $argChildUID1=UID();
                        $argChildUID2=UID();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_insertatlist","inputs": {"INDEX": {"name": "INDEX","block": "'.$argChildUID1.'","shadow": "'.$argChildUID1.'"},"ITEM": {"name": "ITEM","block": "'.$argChildUID2.'","shadow": "'.$argChildUID2.'"}},"fields": {"LIST": {"name": "LIST","id": "'.UID().'","value": "'.trim($arrData[0]).'","variableType": "list"}}, "next": '.($nextUID!=NULL?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').',"shadow": false}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID1.'","opcode": "math_integer","inputs": {},"fields": {"NUM": {"name": "NUM","value": "'.trim($arrFuncData[2]).'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID2.'","opcode": "text","inputs": {},"fields": {"TEXT": {   "name": "TEXT","value": "'.trim($arrFuncData[4],'"').'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        
                     break;
                     case "replace":	//替换第n项数据
                        $argChildUID1=UID();
                        $argChildUID2=UID();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_replaceitemoflist","inputs": {"INDEX": {"name": "INDEX","block": "'.$argChildUID1.'","shadow": "'.$argChildUID1.'"},"ITEM": {"name": "ITEM","block": "'.$argChildUID2.'","shadow": "'.$argChildUID2.'"}},"fields": {"LIST": {"name": "LIST","id": "'.UID().'","value": "'.trim($arrData[0]).'","variableType": "list"}}, "next": '.($nextUID!=NULL?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').',"shadow": false}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID1.'","opcode": "math_integer","inputs": {},"fields": {"NUM": {"name": "NUM","value": "'.trim($arrFuncData[2]).'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID2.'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.trim($arrFuncData[4],'"').'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        
                     break;
                     case "getAt":	//第n项数据
                        $argChildUID=UID();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_itemoflist","inputs": {"INDEX": {"name": "INDEX","block": "'.$argChildUID.'","shadow": "'.$argChildUID.'"}},"fields": {"LIST": {"name": "LIST","id": "'.UID().'","value": "'.trim($arrData[0]).'","variableType": "list"}}, "next": '.($nextUID!=NULL?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').',   "shadow": false,}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID.'","opcode": "math_integer","inputs": {},"fields": {"NUM": {"name": "NUM","value": "'.trim($arrFuncData[2]).'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        
                     break;
                     case "indexOf":	//某个东西第一次出现的编号
                        $argChildUID=UID();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_itemnumoflist","inputs": {"ITEM": {"name": "ITEM","block": "'.$argChildUID.'","shadow": "'.$argChildUID.'"}},"fields": {"LIST": {"name": "LIST","id": "'.UID().'","value": "'.trim($arrData[0]).'","variableType": "list"}}, "next": '.($nextUID!=NULL?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').',"shadow": false}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID.'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.trim($arrFuncData[2],'"').'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        
                     break;
                     case "length":	//列表的项目数
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_lengthoflist","inputs": {},"fields": {"LIST": {"name": "LIST","id": "'.UID().'","value": "'.trim($arrData[0]).'","variableType": "list"}}, "next": '.($nextUID!=NULL?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').',"shadow": false}');
                        
                     break;
                     case "exist":	//列表是否包含东西
                        $argChildUID=UID();
                        array_push($this->Blockly,'{"id": "'.$thisUID.'","opcode": "data_listcontainsitem","inputs": {"ITEM": {"name": "ITEM","block": "'.$argChildUID.'","shadow": "'.$argChildUID.'"}},"fields": {"LIST": {"name": "LIST","id": "'.UID().'","value": "'.trim($arrData[0]).'","variableType": "list"}}, "next": '.($nextUID!=NULL?'"'.$nextUID.'"':$nextUID).',"topLevel": '.$this->bTOPLEVEL.',"parent": '.($parentUID==NULL?'null':'"'.$parentUID.'"').',"shadow": false}');
                        array_push($this->Blockly,'{"id": "'.$argChildUID.'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.trim($arrFuncData[2],'"').'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                        
                     break;

                  }
               }


*/



            }

            return NULL;
         //default
      }

      $this->bTOPLEVEL="false";				//后续积木的toplevel一律为false，除非新开一个代码段。
   }


   /************************************************************************************************************************
   *
   *   利用逆波兰序算法，将复杂的逻辑运算表达式，拆成若干个由两个数组成的简单表达式，每个表达式，在这里被转换成对应的积木。
   *
   *    且非或优先级：
   *    ! > && > ||
   *
   *    大于小于等于，需要等且非或生成后再处理。
   *
   *    这类积木块，需要返回最顶部的那块积木的ID，按照当前算法，是最后生成的积木块的ID。
   *    每块参数积木的ID，都由自己生成，而不是从参数那里传递过来。
   *
   *     $arrLogicExpData：		积木块信息
   *     $parentUID:			上一块积木的UID
   *     返回：arrChildBlockUID:	Array(参数1UID,参数2UID);
   *
   *    逻辑判断不需要shadow
   *
   *
   *    传入的$dataArg为数组，其中任一数据格式为：Array(逻辑运算符,UID,第一个参数,第二个参数)
   *    参数可能是：
   *    1.纯数字
   *    2.变量（仅适用于当前角色的变量和适用于所有角色的变量，以及自制积木的参数）
   *    3.计算表达式
   *    
   *    其中3.计算表达式需要预处理：
   *    1.拆分
   *    2.生成
   *    3.返回最底层积木的UID
   *    4.将返回的UID替换相应的参数
   *    
   *    
   *    
   *
   ************************************************************************************************************************/
   private function parseLogicExpression($arrLogicExpData,$parentUID)
   {
      /*******************************************************************************
         $arrLogicExpData格式

         例1：
            if(  ( 1+2> 50 ) &&1<我的变量 || sensing_mousedown()){}

         解析结果：
            Array
            (
                [0] => Array					//[0]这一组是主逻辑判断数据
                    (
                        [0] => Array
                            (
                                [0] => <				//逻辑运算符
                                [1] => ID_i:wS:8o.5@UFEosZQD2u_DI	//运算符积木UID
                                [2] => 1				//参数1
                                [3] => 我的变量				//参数2 参数可以是数字、字符，或变量，或另一个积木的UID，或者是另一个算术表达式。
                            )
                        [1] => Array
                            (
                                [0] => &&
                                [1] => ID_]hF`7yPfiOOQ@SU7G{KK_DI
                                [2] => ID_jKRoJZnSG8klU@3jiQLU_DI
                                [3] => ID_i:wS:8o.5@UFEosZQD2u_DI
                            )
                        [2] => Array
                            (
                                [0] => ||
                                [1] => ID_jO5hfitcPx5{d?Y09bvl_DI
                                [2] => ID_]hF`7yPfiOOQ@SU7G{KK_DI
                                [3] => ID_t7FkQ6bt]cLnAi9wLonh_DI
                            )
                    )
                [1] => Array					//[1]这一组是子逻辑表达式
                    (							//第[1]组与第[0]组的数据格式是一个样的，不同的是，
                        [0] => Array					//在处理[0]后，需要把最后一组（最底部的积木）的UID返回给调用者。
                            (
                                [0] => >
                                [1] => ID_jKRoJZnSG8klU@3jiQLU_DI
                                [2] => 1+2				//参数1是一个算术表达式
                                [3] => 50
                            )
                    )
                [2] => Array					//[2]这一组是函数调用和算术运算
                    (
                        [0] => Array
                            (
                                [0] => sensing_mousedown
                                [1] => ID_t7FkQ6bt]cLnAi9wLonh_DI
                                [2] => 					//此函数无参数
                            )
                    )
            )
            当[0]存在数据时，需要将[0]的最后一组数据的UID返回给调用者。
            当[0]不存在数据，而[1]存在数据时，需要将[1]的最后一组数据的UID返回给调用者。


         例2：
            if(sensing_mousedown()){}
         解析结果：
            Array
            (
                [2] => Array
                    (
                        [0] => Array
                            (
                                [0] => sensing_mousedown		//这是一个返回布尔值的函数
                                [1] => ID_WZyZHeCLsgr{:F`x$@EF_DI	//由于没有[0]和[1]，所以此时，需要把[2][0]的这个UID返回给调用者。
                                [2] => 
                            )
                    )
            )
            此种数据，没有[0]和[1]，需要将[2]中的第一组数据的UID返回给调用者。


         注：
             1.本函数只处理[0]和[1]中的逻辑表达式数据，
               [2]中的函数调用数据交给parseCalculationExpression()来处理。

             2.如果逻辑表达式中[0]和[1]都没有有效数据，且[2]中也没有返回逻辑表达结果的函数调用，
               则表示$arrLogicExpData无有效的逻辑表达数据。
               此种情况未作处理。

             3.如果逻辑表达式异常，并不会调用此函数进行下一步的处理。
               异常数据形如： if(1+2){} 或 if( operator_round(1.3)){}
               前者Scratch不支持判断中使用纯数字类型参数，后者被调用函数的返回值非布尔值类型。


         关于parent和shadow两个参数的设置：
             1.积木中的参数，除了&&,||和NOT以及需要逻辑判断条件的，其他都需要设置默认值shadow，该条积木数据中shadow值为false。
             2.shadow数据中，parent为null，即使设置了其他值，也不起作用，shadow值为true。
             3.如果积木中参数为数字/字符，则不需要额外添加shadow，该条参数数据中，shadow值为true。

      *******************************************************************************/

      $arrChildBlockUID		= Array(UID(),UID());		//逻辑运算（除了“不成立(!)”）都有两个操作数，所以需要两个ChildBlock，UID由当前生成，并返回到调用处
      $arrChildShadowUID	= Array(UID(),UID());		//逻辑运算（除了“与(&&)”，“或(||)”和“不成立(!)”）中的操作数都是需要默认值积木块Shadow的，UID也是先在这里生成
      $arrLogicArgUID		= Array('','');			//逻辑表达式的参数UID。逻辑表达式是没有默认值的，所以block和shadow合在一处
      $thisUID			= UID();			//条件表达式是由if等引出的，在条件表达式生成完毕后，只需要把最底层积木的UID返回给调用者作为SUBSTACK的值即可，所以thisUID在此处生成。

      /***************************************************************************************************************

          虽然逻辑运算可以用函数表达式来描述，但代数表达式更简洁，更符合大家的使用习惯。
          ------------------------------------------------------------------------------------------------
             逻辑运算符    |      代数表达式       |            函数表达式
          -----------------|---------------------- |------------------------------------------------------
                 >         |        a>b            |   operator_gt(a,b)
                 <         |        a<b            |   operator_lt(a,b)
                 ==        |        a==b           |   operator_equals(a,b)
                 !=        |        a!=b           |   operator_not(a,b)
                 &&        |    (a>b)&&(b>c)       |   operator_and(operator_gt(a,b),operator_gt(b,c))
                 ||        |    (a>b)||(b>c)       |   operator_or(operator_gt(a,b),operator_gt(b,c))
                 !         | !operator_mousedown() |   operator_not(operator_mousedown())
          ------------------------------------------------------------------------------------------------

          其实，在程序中，对函数表达式的处理的实现会更容易些，因为它不用考虑优先级，就是层层的调用，但极难表述。
          代数表达式：
              a>b && b<c || a<b && b>c
          函数表达式：
              operator_or( operator_and( operator_gt( a , b ) ,operator_lt( b , c ) ) , operator_and( operator_lt( a , b ) , operator_gt( b , c ) ) )
          即使是缩减为：
              or( and( gt( a , b ) ,lt( b , c ) ) , and( lt( a , b ) , gt( b , c ) ) )

      *****************************************************************************************************************/
      $arrLogicOptToInfo=Array(/***  运算符与积木名称的对应关系   积木名     参数数量       有无默认值 ****************/
         '>'  => Array("operator_gt",     true,    true  ),	//大于       有两个参数     需要shadow
         '<'  => Array("operator_lt",     true,    true  ),	//小于       有两个参数     需要shadow
         '==' => Array("operator_equals", true,    true  ),	//等于       有两个参数     需要shadow
         '!=' => Array("operator_not",    true,    false ),	//不等于     有两个参数     需要shadow	//这个实际是在原来“=”的基础上，再套一个“不成立”，处理上，有单独的代码控制。
         '&&' => Array("operator_and",    true,    false ),	//与         有两个参数   不需要shadow
         '||' => Array("operator_or",     true,    false ),	//或         有两个参数   不需要shadow
         '!'  => Array("operator_not",    false,   false )	//不成立   只有一个参数   不需要shadow	//不成立不能应用于数字，即如下表达不被支持：“!1”，“!变量名”；如下表达可以正常解析：“1!=2”，“!sensing_mousedown()”，“!我的变量>2”。
      );

      $childLogicBlockUID='';					//需要在最后返回的最底层积木的UID

      /***************************************************************************************************************

         $arrLogicExpData[0]和$arrLogicExpData[1]都是逻辑表达式，
         生成积木的处理的方法完全相同，唯一不同的地方在于：
             1. $arrLogicExpData[0]需要将最后一个积木的UID保存到$childLogicBlockUID，并
             2. 最终返回给调用者。

         $arrLogicExpData[2]是函数调用，需要另行处理。

         所以下面的for循环，只要0和1，即可。

      ***************************************************************************************************************/
      for($n=0;$n<2;$n++)					//两组逻辑判断数据（[0]主逻辑判断和[1]子逻辑判断）
      {
         if(isset($arrLogicExpData[$n]))			//数据可能不存在，所以要确认一下
         {
            $nLEDLength=count($arrLogicExpData[$n]);
            for($i=$nLEDLength-1;$i>=0;$i--)				//正序遍历每一组数据
//            for($i=0;$i<$nLEDLength;$i++)				//正序遍历每一组数据
            {
               //$arrCurrentLogicBlock=$arrLogicExpData[$n][$i];		//当前运算操作符数据：  Array([0]=>逻辑运算符,[1]=>UID,[2]=>第一个参数,[3]=>第二个参数);
               $strOperator=$arrLogicExpData[$n][$i][0];
               $thisUID=$arrLogicExpData[$n][$i][1];			//当前积木的UID

               $arrArgVal=Array(						//积木的两个可能存在的参数
                                trim($arrLogicExpData[$n][$i][2],'"'),		//去掉可能存在的双引号
                                isset($arrLogicExpData[$n][$i][3])?trim($arrLogicExpData[$n][$i][3],'"'):NULL	//“不成立”这个积木，只有一个参数。
                          );
               if($childLogicBlockUID=="")//if($n==0)						//在处理[0]主逻辑判断时，需要返回最后一组数据的UID，
                  $childLogicBlockUID=$thisUID;				//可以通过依次覆盖来获得。
									//当[0]和[1]都不存在时，需要返回[2]中第一组数据的UID。
               /****************************************************************************************************

                  通过操作符，获取对应的opcode、是否需要默认值和是否双参数等配置信息。
                  例如：
                      Array
                      (
                          [0] => Array
                              (
                                  [0] => Array
                                      (
                                          [0] => >
                                          [1] => ID_LA86_RT8PBB}syp?J:f;_DI
                                          [2] => ID_2.W.o0HsB;CJ?jh2Mgrc_DI
                                          [3] => 1
                                      )
                              )
                      )
                  其中：
                      数据[0][0][0]需要查表$arrLogicOptToInfo，获得：
                      Array("operator_gt",    true,  true  );
                      此数据表明，当前应该构建一个opcode为“operator_gt”的积木，且每个参数需要设置shadow默认值，且是两个参数。
                      如果数据获取失败，则表明当前这组数据，不是逻辑表达式。


                      目前观测到，s1,s2,s3,s4都不需要。

               ****************************************************************************************************/
               list($strOPCODE,$bTwoArguments,$bShadowNeeded)=isset($arrLogicOptToInfo[$strOperator])?$arrLogicOptToInfo[$strOperator]:NULL;//运算操作符所对应的积木名称。原本用switch做，但用数组会更快。
               if($strOPCODE==NULL) break;				//未定义的运算符，数据错误，终止当前循环。一旦出错，其实可以直接终止整个程序的运行，但以后可能会增强错误信息的提示，所以暂时这样处理。

               //通过UID查询是否存在parent信息。
               /*************************************************************************************

                  $this->arrBlockToParent用于保存parent与this的关联映射。
                  例如$arrLogicExpData[$n][$i]：
                      Array
                      (
                          [0] => >
                          [1] => ID_LA86_RT8PBB}syp?J:f;_DI
                          [2] => ID_2.W.o0HsB;CJ?jh2Mgrc_DI
                          [3] => 1
                      )
                  其中：
                      数据$thisUID是$arrArgVal[0]的parent，需要保存关联信息：
                      $this->arrBlockToParent[$arrArgVal[0]]=$thisUID;

                  如果通过查表，没有找到相关记录，则parent为函数被调用时传入的参数$parentUID。

               *************************************************************************************/
               $childBlockParent=isset($this->arrBlockToParent[$thisUID])?$this->arrBlockToParent[$thisUID]:$parentUID;		//查表获取parent


               //常量和变量以及非UID的数据，都是要有shadow，shadow不需要parent
               //ID的数据，不需要shadow。
               for($m=0;$m<2;$m++)			//对两个参数依次进行处理，参数的parent为$thisUID。
               {
                  if($m==1 && !$bTwoArguments)	break;	//第二个参数可能不存在，需要确认

                  $strArgVal=$arrArgVal[$m];

                  if($strArgVal=="" || $strArgVal==NULL)			//参数为空，有些积木需要shadow
                  {
                     /********************************************************************************

                         当前算法支持还原部分参数的表达：
                            if( && (  &&  (  >  2)  )  ){}
                            if(1>&& &&){}

                         但下方几例缺失的表达不被支持：
                            if( && (  &&  ( 2 >  )  )  ){}
                            if( && (  &&  (  > )  )  ){}

                         若找到好的算法，会予以修正。

                     ********************************************************************************/

                     //if($bShadowNeeded)				//参数为空，但又需要shadow，于是添加
                     //{
                        //$arrLogicArgUID[$m]=UID();
                        //array_push($this->Blockly, '{"t":"s3","id": "'.$arrLogicArgUID[$m].'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": ""}},"next": null,"topLevel": false,"parent": null,"shadow": true}');//"parent": "'.$thisUID.'","shadow": true}');
                        //$arrArgVal[$m]=$arrLogicArgUID[$m];	
                     //}
                     //else					//不需要shadow，就置NULL
                     //{
                        $arrArgVal[$m]=NULL;
                     //}
                  }
                  else if(is_numeric($strArgVal))	//参数是数字，创建一个同值的shadow，由于是数字，所以不可能是不需要shadow的且或非
                  {
                     $arrChildShadowUID[$m]=$arrLogicArgUID[$m]=UID();		//给shadow生成一个UID。
                     array_push($this->Blockly, '{"t":"2","id": "'.$arrLogicArgUID[$m].'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.$strArgVal.'"}},"next": null,"topLevel": false,"parent":"'.$thisUID.'","shadow": true}');//数字参数，需要parent，且自己就是shadow。
                     $arrArgVal[$m]=$arrLogicArgUID[$m];
                  }
                  else					//其他：变量、自制积木的参数、ID__DI、可解析的算术表达式，或者不可解析的字符串
                  {
                     $arrLogicArgUID[$m]=UID();

                     if(isset($this->arrVariableUIDS[$arrArgVal[$m]]))	//参数是已定义的变量，生成该变量的积木块，
                     {							//并将积木块UID替换原变量名，另外还要生成一个shadow.
                        echo "变量\n";
                        //block变量积木块

//$currentParentUID=$this->arrBlockToParent[$arrLogicArgUID[$m]];
//echo $thisUID;
                        array_push($this->Blockly,    '{"t":"3","id": "'.$arrLogicArgUID[$m].'","opcode": "data_variable","inputs": {},"fields": {"VARIABLE": {"name": "VARIABLE","id": "'.$this->arrVariableUIDS[$arrArgVal[$m]].'","value": "'.$arrArgVal[$m].'","variableType": ""}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": false}');
                        $arrArgVal[$m]=$arrLogicArgUID[$m];		//用IDDI替换原来的参数数据
                        //shadow变量积木块所占位置需要生成shadow
                        //$arrChildShadowUID[0]=UID();
                        //array_push($this->Blockly, '{"t":"s4","id": "'.$arrChildShadowUID[$m].'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": ""}},"next": null,"topLevel": true,"parent": null,"shadow": true}');
                     }
                     else if(isset($this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock][$arrArgVal[$m]])) //对自制积木中的本地变量直接引用
                     {
                        //echo "自制积木的变量\n";
                        //自制积木定义里对参数的调用，只需要：
                        //    VALUE的value为参数名。

//echo "sdfffffffffffffffffff\n";
//print_r($this->arrSelfDefinedFunctionArgs);
//print_r($arrArgVal);
$arrChildShadowUID[$m]=UID();

                        array_push($this->Blockly, '{"t":"5","id": "'.$arrChildShadowUID[$m].'","opcode":"argument_reporter_string_number","inputs": {},"fields": {"VALUE": {"name": "VALUE","value": "'.$arrArgVal[$m].'"}},"next": null,"topLevel": false,"parent":  "'.$thisUID.'","shadow":false}');
                        //$arrArgVal[$m]=$this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock][$arrArgVal[$m]];//UID();//原本传入的是变量名，需要重新生成UID。
                        $arrArgVal[$m]=$arrChildShadowUID[$m];//$this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock][$arrArgVal[$m]];//UID();//原本传入的是变量名，需要重新生成UID。


                        //shadow由调用此变量的积木自己生成
                     }
                     else if(($nFound=preg_match_all("/ID_([^^]*?)_DI/",$arrArgVal[$m]))>0)	//检测是否是积木块UID。“ID_xxxxxxxxxxxxxxxxxxxx_DI”为两个RPN所独有。
                     {
                        /*************************************************************************************

                            preg_match只能用来判断是否有，而不能判断是不是。
                            preg_match_all可以判断有几个。
                            
                               IDDI + 1;
                               IDDI + IDDI;
                               A+IDDI

                        **************************************************************************************/

                        //if($bShadowNeeded)				//需要shadow
                        //{
                           //$arrChildShadowUID[$m]=UID();
                           //array_push($this->Blockly, '{"t":"s2","id": "'.$arrChildShadowUID[$m].'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": ""}},"next": null,"topLevel": false,"parent": null,"shadow": true}');//"parent": "'.$thisUID.'","shadow": true}');
                        //}

                        $strIDDI=trim($arrArgVal[$m]);

                        if(strlen($strIDDI)==26)				//唯一的纯IDDI数据，为26字节长：ID_XXXXXXXXXXXXXXXXXXXX_DI
                        {
                           //echo "保持映射关系。\n";
                           $this->arrBlockToParent[$arrArgVal[$m]]=$thisUID;	//保存child与parent的映射关系

                        }
                        else							//可能是一个算术表达式
                        {
                           //如果是积木块UID，不需要任何转换；如果不是，就需要通过RPN进行解析，因为当前是计算表达式。
                           //解析计算表达式
                           if($this->rpn_calc->init($strIDDI)==TRUE)		//将四则混合运算字符串交由RPN来完成解析
                           {
                              $arrArgCalc=$this->rpn_calc->toScratchJSON();	//生成符合Scratch3.0要求的数组数据
                              $T=$this->parseCalculationExpression(Array('NUM','math_number','NUM'),$arrArgCalc,$thisUID);//,$arrChildBlockUID); //生成积木块，并将返回的最后一个积木的UID，替换原来的算数表达式。
                              $arrArgVal[$m]=$T[0];
                           }
                           //else 解析失败未处理
                        }
                     }
                     else//其他情况：数学表达式，不合法的变量名
                     {
                        /********************************************************************************************

                           $arrArgVal

                           Array
                           (
                               [0] => 1+3
                               [1] => 3
                           )

                        ********************************************************************************************/

                        //if($bShadowNeeded)				//需要shadow，shadow不需要parent
                        //{
                           //$arrChildShadowUID[$m]=UID();
                           //array_push($this->Blockly, '{"t":"s1","id": "'.$arrChildShadowUID[$m].'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": ""}},"next": null,"topLevel": false,"parent":null,"shadow": true}');//shadow不需要parent。"parent": "'.$thisUID.'","shadow": true}');	
                        //}

                        //解析计算表达式
                        if($this->rpn_calc->init($arrArgVal[$m])==TRUE)		//将四则混合运算字符串交由RPN来完成解析
                        {
                           $arrArgCalc=$this->rpn_calc->toScratchJSON();	//生成符合Scratch3.0要求的数组数据
                           $T=$this->parseCalculationExpression(Array('NUM','math_number','NUM'),$arrArgCalc,$thisUID);//,$arrChildBlockUID); //生成积木块，并将返回的最后一个积木的UID，替换原来的算数表达式。
                           $arrArgVal[$m]=$T[0];
                        }
                        else//不是算术表达式，只能作为字符串处理。由于此处只能数字，所以显示空。
                        {
                           $arrChildShadowUID[$m]=$arrLogicArgUID[$m]=UID();	//给shadow生成一个UID。

                           //t:6是t:5的shadow
                           array_push($this->Blockly, '{"t":"6","id": "'.$arrLogicArgUID[$m].'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.$arrArgVal[$m].'"}},"next": null,"topLevel": false,"parent":"'.$thisUID.'","shadow": true}');//"parent": "'.$thisUID.'","shadow": true}');
                           $arrArgVal[$m]=$arrLogicArgUID[$m];
                        }
                        //积木块不需要shadow
                     }
                     $arrChildBlockUID[$m]=$arrArgVal[$m];			//将算术运算符最后一块积木的UID替换原参数。
                  }
               }//for $m

               //前面生成了参数的积木块，后面开始生成主积木块。

               //且或非不需要ShadowBlock
               if($strOPCODE=="operator_not")				//非有两个操作格式，一个双目，一个单目。
               {
                  if($bTwoArguments)					//双目操作，也就是格式为：a!=b  //这个是明确为不等于!=，它还可以不大于，不小于，则由else来实现。
                  {
                     $childEqualsBlockUID=UID();

                     if(is_numeric($arrArgVal[0]))			//如果是数字，生成
                     {
                        $childEqualsArg1=UID();
                        array_push($this->Blockly,'{"t":"7","id": "'.$childEqualsArg1.'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.$arrArgVal[0].'"}},"next": null,"topLevel": false,"parent": "'.$childEqualsBlockUID.'","shadow": true}');
                     }
                     else							//如果非数字，表示前面已经生成过shadow了，现在直接使用。
                        $childEqualsArg1=$arrArgVal[0];

                     if(is_numeric($arrArgVal[1]))
                     {
                        $childEqualsArg2=UID();
                        array_push($this->Blockly,'{"t":"8","id": "'.$childEqualsArg2.'","opcode": "text","inputs": {},"fields": {"TEXT": {"name": "TEXT","value": "'.$arrArgVal[1].'"}},"next": null,"topLevel": false,"parent": "'.$childEqualsBlockUID.'","shadow": true}');
                     }
                     $childEqualsArg2=$arrArgVal[1];
                     array_push($this->Blockly,'{"t":"9","id": "'.$childEqualsBlockUID.'","opcode": "operator_equals","inputs": {"OPERAND1": {"name": "OPERAND1","block": "'.$childEqualsArg1.'","shadow": "'.$childEqualsArg1.'"},"OPERAND2": {"name": "OPERAND2","block": "'.$childEqualsArg2.'","shadow": "'.$childEqualsArg2.'" }},"fields": {},"next": null,"topLevel": false,   "parent":"'.$thisUID.'","shadow": false}');

                     array_push($this->Blockly,'{"t":"10","id": "'.$thisUID.'","opcode": "operator_not","inputs": {"OPERAND": {"name": "OPERAND","block": "'.$childEqualsBlockUID.'","shadow": null}  },"fields": {},"next": null,"topLevel": '.($childBlockParent==NULL?'true':'false').',"parent": '.($childBlockParent==NULL?'null':'"'.$childBlockParent.'"').',"shadow": false}');

                  }
                  else//单目操作，也就是格式为：!a 
                  {
                     array_push($this->Blockly,'{"t":"11","id": "'.$thisUID.'","opcode": "operator_not","inputs": {"OPERAND": {"name": "OPERAND","block": "'.$arrArgVal[0].'","shadow": null}  },"fields": {},"next": null,"topLevel": '.($childBlockParent==NULL?'true':'false').',"parent": '.($childBlockParent==NULL?'null':'"'.$childBlockParent.'"').',"shadow": false}');
                  }
               }
               else //大于、小于、等于、且、或
               {

echo "t12sssssssssssssssssssssss\n";
print_r($arrArgVal);
                  array_push($this->Blockly,'{"t":"12","id": "'.$thisUID.'","opcode": "'.$strOPCODE.'","inputs": {"OPERAND1": {"name": "OPERAND1","block": "'.$arrArgVal[0].'","shadow": '.(($bShadowNeeded)?'"'.$arrChildShadowUID[0].'"':'null').'},"OPERAND2": {"name": "OPERAND2","block": "'.$arrArgVal[1].'","shadow": '.(($bShadowNeeded)?'"'.$arrChildShadowUID[1].'"':'null').'}},"fields": {},"next": null,"topLevel": '.($childBlockParent==NULL?'true':'false').',"parent": '.($childBlockParent==NULL?'null':'"'.$childBlockParent.'"').',"shadow": false}');
               }
            }//for $i

         }//if(isset()) //逻辑判断积木生成

      }//for $n

      if(isset($arrLogicExpData[2]))//函数调用
      {
         /*****************************************************************************************

            逻辑运算数据有三大组：

                Array
                (
                     [0]=>Array(),	//主逻辑表达式拆分数据
                     [1]=>Array(),	//子逻辑表达式拆分数据
                     [2]=>Array()	//函数调用拆分数据
                )

            算术运算数据只有两大组：

                Array
                (
                     [0]=>Array(),	//算术表达式拆分数据
                     [1]=>Array()	//函数调用拆分数据
                )

             所以在处理函数和算术表达式的数据时，需要构建数组，把数据放在偏移量[1]处：Array(NULL,$arrLogicExpData[2]);
             且由于这[2]中的数据都是函数调用，它们的配置信息可以直接查询，所以parseCalculationExpression的第一个参数为NULL，
             
         ******************************************************************************************/


         if($childLogicBlockUID=="")		//前面$arrLogicExpData[0]和$arrLogicExpData[1]都没有数据，则对$arrLogicExpData[2]进行处理后的返回值需要被返回。
            $childLogicBlockUID=$this->parseCalculationExpression(NULL,Array(NULL,$arrLogicExpData[2]),$parentUID);	//当第一个参数是NULL时，表示这些数据是逻辑表达式拆分出来的。
         else
            $this->parseCalculationExpression(NULL,Array(NULL,$arrLogicExpData[2]),$parentUID);	//当第一个参数是NULL时，表示这些数据是逻辑表达式拆分出来的。

//BUG BUG BUG
//if(1>operator_round(我的变量+2*3)){}
//print_r($arrLogicExpData[2]);
//echo $childLogicBlockUID."ddddddddddddddddddddddddddddddddddddddd\n";

         if(is_array($childLogicBlockUID)) 	//返回数据未统一导致。稍后检查。
            return $childLogicBlockUID;
         else
            return Array($childLogicBlockUID,$childLogicBlockUID);
      }
      return Array($childLogicBlockUID,$childLogicBlockUID);
   }


   /************************************************************************************************************************
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
   *    $arrChildArgBlockInfo：	Array(参数字段名INPUT/FIELD， 参数名STEPS/ANGLE/TEXT，数据类型math_number，参数类型NUM);//从$arrArgumentConfig里获取
   *    $arrCalExpData：	积木块信息
   *    $parentuid:		上一块积木的UID
   *    返回：arrChildUID:      Array(参数1UID,参数2UID);	//实际只需要返回一个UID，shadow数据由主调积木块生成。
   *
   *
   *    算法已改进，积木块信息如果是数组，可以不提交parentUID。
   *    calc的拆分，是彻底拆分，不存在需要再次拆分的现象，除非出现函数调用。
   *
   *    对于+，-，*，/，直接在这里生成
   *    对于函数，调用parseGeneralBlocks();
   *
   ************************************************************************************************************************/
   private function parseCalculationExpression($arrChildArgBlockInfo,$arrCalExpData,$parentUID)
   {
echo "BUGGGGGGGGGGGGGGGGGGGGG\n";
print_r($arrCalExpData);
      /********************************************************************************************************************

         本函数主要生成算术计算和函数调用这两类积木。

         算术计算（+，-，*，/）积木
             每个算术计算积木，都有两个inputs类参数，
             每个参数，都可以是数字，变量，另一个算术计算积木，或返回数值的函数。


         函数分数学函数和普通函数：
             1.数学函数（绝对值，向下取整，向上取整，平方根，三角函数，对数函数，指数函数等）
               这类函数，实际都是由operator_mathop加上相应的参数来实现的。
                   第一个参数为fields类型，参数值为：abs,floor,ceiling,sqrt,sin,cos,tan,asin,acos,atan,ln,log,e ^,10 ^
                   第二个参数为inputs类型，参数值可以是数字，也可以是变量名，或者是另一个算术计算积木或返回数值的函数。
             2.普通函数
               这类函数比较杂，有统计字符串长度的，获取字符串中某一个字符的，有对数字进行四舍五入的，有求余的，有生成随机数的，
               这类函数的所有参数都在一个字符串中，未拆分。

         每个inputs类参数都需要一个额外的子积木用于存放数据。

         例如：
             motion_gotoxy(  10 + operator_random( 1,10 )   ,10 + operator_random( 1+2,10 ) );

         第一个参数解析后：
             Array
             (
                 [0] => Array						//[0]算术运算
                     (
                         [0] => Array
                             (
                                 [0] => +					//+,-,*,/运算
                                 [1] => ID_c7WrtMXPDpdm1`K{~NQ=_DI          	//此积木的UID
                                 [2] => 10					//两个参数已经拆分
                                 [3] => ID_?bxo?]Xu~Hs74P6SF2?2_DI		//参数可以是另一个积木的UID，也可以是调用另一个函数
                             )
                     )
                 [1] => Array						//[1]函数调用
                     (
                         [0] => Array
                             (
                                 [0] => operator_random				//函数名
                                 [1] => ID_?bxo?]Xu~Hs74P6SF2?2_DI		//此积木的UID
                                 [2] => 1,10					//此函数的全部参数，未拆分
                             )
                     )
             )

      *********************************************************************************************************************/
      $arrChildBlockUID  = Array(UID(),UID());	//参数如果是计算公式，则已被拆分成一个操作符加两个数的形式。
						//这两个数，需要额外生成两个积木控件，也就需要两个UID。

      $childCalcBlockUID='';			//这个UID值，用于返回给调用者。

      if(is_array($arrCalExpData))		//当前参数是数组，是计算表达式被拆分后的结果
      {
         /*****************************************************************************************************************

            此函数有两种调用方式：
                1.完整版调用
                    $arrChildArgBlockInfo!=NULL

                2.由parseLogicExpression调用
                    $arrChildArgBlockInfo==NULL

            当为第2种调用方式时，表示只需要处理$arrCalExpData[1]中的数据，这些数据皆为函数调用。
            但是，如果表达式完全由函数调用组成，则$arrCalExpData[0]也可能为空。

            例如：
                operator_random(operator_random(4,10),10);
            解析后数据：
                Array
                (
                    [0] => Array ()						//无算术表达式
                    [1] => Array						//仅有函数调用
                        (
                          [0] => Array
                              (
                                  [0] => operator_random
                                  [1] => ID_1wo;vK._BC1]Q:P]SG#7_DI
                                  [2] => ID_PJ#xCq[6Df0BmC]YC5R{_DI ,10
                              )
                          [1] => Array
                              (
                                  [0] => operator_random
                                  [1] => ID_PJ#xCq[6Df0BmC]YC5R{_DI
                                  [2] => 4,10
                              )
                        )
                )

         ******************************************************************************************************************/
         if($arrChildArgBlockInfo==NULL)    unset($arrCalExpData[0]);		//下方for循环里是“有什么就处理什么”，为确保第2种调用方式不会重复生成，故将[0]直接删除

         for($n=0;$n<2;$n++)					//大部分是有两个参数的
         {
            if(isset($arrCalExpData[$n]))			//直接用isset检测[0]和[1]是否存在。
            {

echo "-------存在--------------$n \n";
print_r($arrCalExpData[$n]);

               $nCEDLength=count($arrCalExpData[$n]);
//               for($i=0;$i<$nCEDLength;$i++)					//遍历生成每一组数据

               for($i=$nCEDLength-1;$i>=0;$i--)					//遍历生成每一组数据
               {
                  $arrCurrentCalcBlock=$arrCalExpData[$n][$i];
                  $strOperator=$arrCalExpData[$n][$i][0];
                  $thisUID=$arrCalExpData[$n][$i][1];				//当前积木的UID将是添加的每个inputs类变量积木的parent

                  $arrArgVal=Array(						//积木的两个可能存在的参数
                                trim($arrCalExpData[$n][$i][2],'"'),		//去掉可能存在的双引号
                                isset($arrCalExpData[$n][$i][3])?trim($arrCalExpData[$n][$i][3],'"'):NULL	//“不成立”这个积木，只有一个参数。
                  );

echo "ffffffffffffff\n";
print_r($arrCurrentCalcBlock);
                  /*******************************************************************************************************

                     $arrCalExpData[0]的最后一个积木，是所有积木的最底层积木，它的UID需要返回给调用者。
                     但当$arrCalExpData[0]不存在时，就需要将$arrCalExpData[1]的最后一组数据的UID返回给调用者。
                     由于生成算法是倒着的，所以也就是只返回第一组解析的数据的UID。

                  ********************************************************************************************************/
                  //if($n==1){
                     if($childCalcBlockUID=='')					//当前已经为[1]了，而$childCalcBlockUID仍为空，也暗含了[0]不存在。
                        $childCalcBlockUID=$thisUID;				//第一组数据为参数底层积木，需要把这组数据的UID返回给主调积木。
                  //}
                  //else
                  //   $childCalcBlockUID=$thisUID;				//最后一组数据为参数的底层积木，需要把这组数据的UID返回给主调积木。

                  /****************************************************************************************

                  motion_movesteps( operator_round(1+ 2 * 3  ) );
                  在第一层对motion_movesteps的参数解析时，
                  [0]是以最后一组为最底层积木

                  而在第二层对operator_round的参数解析时，
                  因为积木间会有parent问题，所以把最底层积木放到了最顶层。

                  ******************************************************************************************/


                  /*******************************************************************************************************

                     $this->arrBlockToParent保存了每一个子积木的父积木数据。

                     例如：
                         Array(
                             [0] => Array（						//[0]逻辑运算
                                     [0] => Array(
                                             [0] => +					//+,-,*,/运算
                                             [1] => ID_c7WrtMXPDpdm1`K{~NQ=_DI          //此积木的UID
                                             [2] => 10
                                             [3] => ID_?bxo?]Xu~Hs74P6SF2?2_DI		//参数可以是另一个积木的UID
                                         )
                                 )
                             [1] => Array(						//[1]函数调用
                                     [0] => Array(
                                             [0] => operator_random			//函数名
                                             [1] => ID_?bxo?]Xu~Hs74P6SF2?2_DI		//此积木的UID
                                             [2] => 1,10				//此函数的全部参数，未拆分状态
                                         )
                                 )
                         )
                      在解析[0][0]数据时，由于该组数据是[0]段的第一组数据，所以 $this->arrBlockToParent['ID_c7WrtMXPDpdm1`K{~NQ=_DI'] 是不存在的，
                      因此，该积木的parent就是函数的第三个参数：$parentUID。
                      同时，由于[0][0][3]是一个ID_xxxxxxxxxxxxxxxxxxxx_DI数据（也即它是另一个积木的UID），因此需要在 $this->arrBlockToParent 中添加一条数据：
                          $this->arrBlockToParent['ID_?bxo?]Xu~Hs74P6SF2?2_DI']='ID_c7WrtMXPDpdm1`K{~NQ=_DI';

                      在解析[1][0]数据时，通过查表，发现 $this->arrBlockToParent['ID_?bxo?]Xu~Hs74P6SF2?2_DI'] 存在，
                      于是，该积木的parent就是“ID_c7WrtMXPDpdm1`K{~NQ=_DI”。

                  *********************************************************************************************************/
                  $childBlockParent=isset($this->arrBlockToParent[$thisUID])?$this->arrBlockToParent[$thisUID]:$parentUID;	//当前积木的parent

                  /********************************************************************************************************

                      前面已经论述过，为了书写和表达的方便，计算表达式用了“+,-,*,/,%”这些运算符号，
                      而这些运算符号，实际对应了“operator_add,operator_subtract,operator_multiply,operator_divide,operator_mod”等opcode，
                      要重建积木，就必须使用opcode，因此这里有一个转换的操作。
                      另外，“+,-,*,/,%”的处理，与函数的处理有不同，所以$arrCalcOptInfo里还有操作类型控制数据。

                  *********************************************************************************************************/

echo $strOperator;
                  $arrCalcOptInfo=isset($this->arrCalcFunctionToActuralOpcode[$strOperator])?$this->arrCalcFunctionToActuralOpcode[$strOperator]:NULL;
echo "这里有问题\n";
print_r($arrCalcOptInfo);


                  if($arrCalcOptInfo==NULL) break;					//未定义的运算符，数据错误，终止当前循环

                  /********************************************************************************************************

                      在将积木运算符转换成opcode后，就可以得到该opcode在构建积木数据时所需要的全部参数配置信息。
                      配置信息格式：
                      Array(
                           "fields"=>Array(						//下拉菜单参数
                                       Array("字段名1","参数1类型")				//此处“参数1类型”数据为可选。有些积木有多个参数或者一个都没有。
                                   ),
                           "inputs"=>Array(						//接收从键盘输入的文本或数字参数，也可接收变量或计算公式。
                                       Array("字段名2","参数2的opcode","参数2类型"),		//有些积木有多个参数，或者一个都没有。
                                   )
                      )

                  *********************************************************************************************************/
                  $argInfo=isset($this->arrArgumentConfig[$arrCalcOptInfo[0]])?$this->arrArgumentConfig[$arrCalcOptInfo[0]]:NULL;//当前如果不是运算符而是函数调用，则需要获取函数的配置信息
echo "9999999999999999999999999999999\n";

print_r($arrCalcOptInfo);
print_r($argInfo);

print_r($this->arrBlockToParent);
                  $nInputsLength=count($argInfo["inputs"]);				//inputs参数个数
                  if($nInputsLength>0)							//函数中调用函数，需要重新获取函数的配置数据
                  {
                     if($arrCalcOptInfo[1]>0)						//[0]：“+,-,*,/,%”  [1]：函数调用
                     {
                        $arrInputVal=Array();
                        for($a=0;$a<$nInputsLength;$a++)				//为了去掉若干个Warnning，也是没辙了。
                           $arrInputVal[]=NULL;

                        $nCCBVariableLength=strlen($arrArgVal[0]);
                        $nVCounter=0;

                        /***************************************************************************************************

                            多个参数，直接按“,”来拆分。

                            如果有多个参数的函数里再调用有多个参数函数，会出现多个“,”，
                            但在之前，数据已经被彻底拆分。

                            例如：
                                motion_gotoxy( operator_random(1,10) ,operator_random(100,operator_random(100000,10000000)));

                            解析后结果：
                                Array(
                                    [0] => Array(
                                            [0] => Array()
                                            [1] => Array(
                                                    [0] => Array(
                                                            [0] => operator_random
                                                            [1] => ID_;?5Et@D53cDy~YP9LMd1_DI
                                                            [2] => 1,10
                                                        )
                                                )
                                        )
                                    [1] => Array(
                                            [0] => Array()
                                            [1] => Array(
                                                    [0] => Array(
                                                            [0] => operator_random
                                                            [1] => ID_0MQUHUKtz}QLFkAmQ`1T_DI
                                                            [2] => 100, ID_LwRAZqb#{UntGh:gC9Sn_DI	//这里出现了空格，拆分后需要做好trim()防护。
                                                        )
                                                    [1] => Array(
                                                            [0] => operator_random
                                                            [1] => ID_LwRAZqb#{UntGh:gC9Sn_DI
                                                            [2] => 100000,10000000
                                                        )
                                                )
                                        )
                                )

                        ***************************************************************************************************/
                        for($l=0;$l<$nCCBVariableLength;$l++)						//拆分参数
                        {
                           $chCH=$arrArgVal[0][$l];
                           if($chCH==',') {$nVCounter++;$l++;}				//以“,”拆分	//
                           $arrInputVal[$nVCounter].=$arrArgVal[0][$l];
                        }

                        $arrArgVal[0]=trim(trim($arrInputVal[0]),'"');			//将拆分好的参数再覆盖回去。
                        if($nVCounter>0)						//$nVCounter：0|1
                           $arrArgVal[1]=trim(trim($arrInputVal[1]),'"');		//第二个参数如果有，要加上。
                     }

                     /***************************************************************************************************

 	                 重新生成积木参数配置数据
                         原本调用时是提供了，但是固定的值，不是很精准，现在通过查字典，获得的配置数据更精准。
                         由于重新生成了该数据，所以在parseLogicExpression里调用的本函数，即使第一个参数为NULL，也不会受任何影响。

                         配置信息格式：
                             Array(
                                   "fields"=>Array(						//下拉菜单参数
                                                  Array("字段名1","参数1类型")				//此处“参数1类型”数据为可选。有些积木有多个参数，或者一个都没有。
                                           ),
                                   "inputs"=>Array(						//接收从键盘输入的文本或数字参数，也可接收变量或计算公式。
                                                  Array("字段名2","参数2的opcode","参数2类型")		//有些积木有多个参数，或者一个都没有。
                                           )
                             )
                         例如：
                              "motion_gotoxy" => Array("fields"=>Array(),"inputs"=>Array(Array("X","math_number","NUM"),Array("Y","math_number","NUM")));

                     ***************************************************************************************************/

                     $arrChildArgBlockInfo[0]=$argInfo["inputs"][0][0];			//参数积木的字段名
                     $arrChildArgBlockInfo[1]=$argInfo["inputs"][0][1];			//参数积木的opcode
                     $arrChildArgBlockInfo[2]=$argInfo["inputs"][0][2];			//参数数据类型
                  }
                  else									//无inputs参数
                  {
                     //if($n==0)
                     //   $arrChildArgBlockInfo=Array($arrCalExpData[0][0][0],$arrCalExpData[0][0][1],$arrCalExpData[0][0][2]);
                     //else
                        $arrChildArgBlockInfo=Array(NULL,NULL,NULL);			//无inputs，则配置数据全为NULL
                  }

                  //每个运算符/函数最多有两个参数，每个参数都需要另外生成一份积木数据，如果该参数需要默认值，则还需要生成shadow。
                  $arrArgBlockUID=Array(UID(),UID());					//生成两个参数的block的UID
                  $arrArgShadowUID=$arrArgBlockUID;					//两个参数的shadow默认与block相同，不同就表示是变量或者其他积木。

                  /********************************************************************************************************

                      当解析、拆分完积木的参数数据，获取到要构建积木所需要的配置信息$argInfo后，就要开始重建积木数据了。

                  *********************************************************************************************************/

                  $arrChildUIDX=Array(NULL,NULL);

                  for($m=0;$m<2;$m++)							//构建两个参数的积木，有shadow的需要一起创建
                  {
                     if(isset($argInfo['inputs'][$m]))					//如果有inputs参数配置信息
                     {

                        if(!isset($arrArgVal[$m]) || $arrArgVal[$m]==NULL) continue;			//无此数据，就下一个循环
                        //else if($arrArgVal[$m]==NULL)				//参数为空，无数据。不需要shadow。这种情况一般是没有输入，也可能程序出错。
                        //{
                        //   echo "\n[INFO]: $m 无参数\n";
                        //   //print_r($arrChildArgBlockInfo);
                        //   //array_push($this->Blockly, '{"d":"0","id": "'.$arrChildArgBlockInfo[1].'","opcode": "'.$arrChildArgBlockInfo[0].'" ,"inputs": {},"fields": {},"next": null,"topLevel": false,"parent": "'.$parentUID.'","shadow": false}');
                        //   continue;
                        //}
                        else if(is_numeric($arrArgVal[$m]))			//纯数字参数，直接使用，不需要shadow。UID 为 $arrArgBlockUID[$m]， ShadowID 与 UID 一致。
                        {
                           array_push($this->Blockly, '{"d":"1","id": "'.$arrArgBlockUID[$m].'","opcode": "'.$arrChildArgBlockInfo[1].'" ,"inputs": {},"fields": {"'.$arrChildArgBlockInfo[2].'": {"name": "'.$arrChildArgBlockInfo[2].'","value": "'.$arrArgVal[$m].'"}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": true}');
                           //continue;
                        }
                        else
                        {
                           $arrArgShadowUID[$m]=UID();					//生成一个新的ShadowID
                           if(isset($this->arrVariableUIDS[$arrArgVal[$m]]))//in_array($arrArgVal[0],$this->arrVariables) )	//已定义变量，需要额外加一个Shadow,，ID 为 $arrArgBlockUID[0] 和 $arrArgShadowUID[0]
                           {
                              //已定义的变量
                              //block
                              array_push($this->Blockly, '{"d":"2","id": "'.$arrArgBlockUID[$m].'","opcode": "data_variable","inputs": {},"fields": {"VARIABLE": {"name": "VARIABLE","id": "'.$this->arrVariableUIDS[$arrArgVal[$m]].'","value": "'.$arrArgVal[$m].'","variableType": ""}},"next": null,"topLevel": false,"parent": "'.$thisUID.'","shadow": false}');
                              //shadow
                              array_push($this->Blockly, '{"d":"3","id": "'.$arrArgShadowUID[$m].'","opcode": "'.$arrChildArgBlockInfo[1].'" ,"inputs": {},"fields": {"'.$arrChildArgBlockInfo[2].'": {"name": "'.$arrChildArgBlockInfo[2].'","value": ""}},"next": null,"topLevel": true,"parent": null,"shadow": true}');//"parent":  "'.$thisUID.'","shadow": true}');
                              //continue;
                           }

                           else if(isset($this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock][$arrArgVal[$m]]))	//对自制积木中的本地变量直接引用
                           {
                              //echo "自制积木的变量：";
                              $this->arrBlockToParent[$arrArgVal[$m]]=$this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock][$arrArgVal[$m]];	//保存child与parent的映射关系

                              $arrArgBlockUID[$m]=$this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock][$arrArgVal[$m]];//$arrArgVal[0];				//直接使用该ID
                              $arrArgShadowUID[$m]=UID();
                              //block对变量的直接引用
                              array_push($this->Blockly, '{"d":"4","id": "'.$arrArgBlockUID[$m].'","opcode":"argument_reporter_string_number","inputs": {},"fields": {"VALUE": {"name": "VALUE","value": "'.$arrArgVal[$m].'"}},"next": null,"topLevel": false,"parent":  "'.$thisUID.'","shadow":false}');
                              //shadow
                              array_push($this->Blockly, '{"d":"5","id": "'.$arrArgShadowUID[$m].'","opcode": "'.$arrChildArgBlockInfo[1].'" ,"inputs": {},"fields": {"'.$arrChildArgBlockInfo[2].'": {"name": "'.$arrChildArgBlockInfo[2].'","value": ""}},"next": null,"topLevel": true,"parent":null,"shadow": true}');//"parent":  "'.$thisUID.'","shadow": true}');
                              //continue;
                           }

                           else if( strlen(trim($arrArgVal[$m]))==26 && preg_match("/ID_([^^]*?)_DI/",$arrArgVal[$m])==1)	//指向另一个积木，需要额外加一个Shadow
                           {

                              //if(strlen(trim($arrArgVal[$m]))==26 )
                              //{
                              //指向另一个积木
                              $this->arrBlockToParent[$arrArgVal[$m]]=$thisUID;	//保存child与parent的映射关系
                              $arrArgBlockUID[$m]=$arrArgVal[$m];				//直接使用该ID
                              array_push($this->Blockly, '{"d":"6","id": "'.$arrArgShadowUID[$m].'","opcode":"'.$arrChildArgBlockInfo[1].'","inputs": {},"fields": {"'.$arrChildArgBlockInfo[2].'": {"name": "'.$arrChildArgBlockInfo[2].'","value": ""}},"next": null,"topLevel": false,"parent":  null,"shadow": true}');//"parent":  "'.$thisUID.'","shadow": true}');
                              //}
                              //else
                              //{//算数表达，这里应该是早被拆开了，所以不会有这个else了。
                              //}
                              //continue;
                           }

                           else//啥也不是时，可能是文本字符串。
                           {
                              //if(strpos($arrArgVal[$m],'"')===FALSE)		//没有被双引号括起来，就尝试按照计算表达式来处理一下
                              if($arrArgVal[$m][0]!='"')		//没有被双引号括起来，就尝试按照计算表达式来处理一下
                              {

echo "继续解构。";
                                 $arrLoopCondition=$this->rpn_calc->init($arrArgVal[$m]);
                                 if($arrLoopCondition===TRUE)					//拆分成功
                                 {
                                    $arrLoopCondition=$this->rpn_calc->toScratchJSON();
                                    echo "条件结果：";
                                    print_r($arrLoopCondition);

                                    $nLCLength=count($arrLoopCondition[0]);

                                    $arrChildUIDX[$m]=$this->parseCalculationExpression(Array("NUM","math_number","NUM"),$arrLoopCondition,$thisUID);//$arrLoopCondition,NULL);

//echo "this UID".$thisUID."\n";
//echo "UID child:\n";
                                    //print_r($arrChildUIDX);
                                    $arrArgBlockUID[$m]=$arrArgBlockUID[$m]=$arrChildUIDX[$m][0];
                                    $childBlockParent=$parentUID;

//这里应该要保存一份对应关系的。
                                    $this->arrBlockToParent[$arrChildUIDX[$m][0]]=$thisUID;


                                    //这个是shadow，要补上。
                                    array_push($this->Blockly, '{"d":"x8","id":"'.$arrArgShadowUID[$m].'","opcode": "'.$arrChildArgBlockInfo[1].'","inputs": {},"fields": {"TEXT": {"name": "TEXT","id":"'.$arrChildUIDX[$m][0].'","value": "'.trim($arrArgVal[$m],'"').'"}},"next": null,"topLevel":true,"parent":null,"shadow":true}');//"topLevel": '.($thisUID==NULL?'true':'false').',"parent":'.($thisUID==NULL?'null':'"'.$thisUID.'"').',"shadow":false}');//"parent": "'.$thisUID.'","shadow": true}');
                                 }
                                 else//d:82同d:8
                                 {
                                    //join( "找到一组解：" ,"香蕉" ) 
                                    array_push($this->Blockly, '{"d":"82'.$m.'","id":"'.$arrArgShadowUID[$m].'","opcode": "'.$arrChildArgBlockInfo[1].'","inputs": {},"fields": {"'.$arrChildArgBlockInfo[2].'": {"name": "'.$arrChildArgBlockInfo[2].'","value": "'.trim($arrArgVal[$m],'"').'"}},"next": null,"topLevel": false,"parent":"'.$thisUID.'","shadow": true}');//"parent": "'.$thisUID.'","shadow": true}');
                                    $arrArgBlockUID[$m]=$arrArgShadowUID[$m];	//block和shadow保持一致
                                 }
                              }

                              /*************************************************************************************************************
				 Array
				 (
 				     [0] => operator_mathop
  				     [1] => ID_J_f{{2HtY0V#.GtjlZ0Z_DI
  				     [2] => "sin"				//函数类型
  				     [3] => 2					//函数参数
				 )
                              *************************************************************************************************************/

                              else
                              {
                                 if($strOperator=="operator_mathop")
                                    array_push($this->Blockly, '{"d":"7","id":"'.$arrArgShadowUID[$m].'","opcode": "'.$arrChildArgBlockInfo[1].'","inputs": {},"fields": {"'.$arrChildArgBlockInfo[2].'": {"name": "'.$arrChildArgBlockInfo[2].'","value": "'.trim($arrArgVal[1],'"').'"}},"next": null,"topLevel": false,"parent":"'.$thisUID.'","shadow": true}');//"parent": "'.$thisUID.'","shadow": true}');
                                 else
                                 {
                                    array_push($this->Blockly, '{"d":"8'.$m.'","id":"'.$arrArgShadowUID[$m].'","opcode": "'.$arrChildArgBlockInfo[1].'","inputs": {},"fields": {"'.$arrChildArgBlockInfo[2].'": {"name": "'.$arrChildArgBlockInfo[2].'","value": "'.trim($arrArgVal[$m],'"').'"}},"next": null,"topLevel": false,"parent":"'.$thisUID.'","shadow": true}');//"parent": "'.$thisUID.'","shadow": true}');
                                 }
                                 $arrArgBlockUID[$m]=$arrArgShadowUID[$m];	//block和shadow保持一致
                              }
                              //continue;
                           }
                        }
                     }//if isset


                  }//for $m  //参数构建完毕

                  //创建算术计算积木块
                  //上面添加的都是参数的shadow，这里才是操作符积木块。
                  if($arrChildArgBlockInfo[0]==NULL)			//被调用的函数无参数
                  {
                     //if($n==0)					//
                     //{
                     //   $currentParentUID=$this->arrBlockToParent[$arrCalExpData[0][1]];
                     //   array_push($this->Blockly, '{"d":"9","id": "'.$arrCalExpData[0][1].'",    "opcode": "'.$arrCalExpData[0][0].'",    "inputs": {},    "fields": {},    "next": null,    "topLevel":'.($currentParentUID==NULL?'true':'false').',    "parent": '.($currentParentUID==NULL?'null':'"'.$currentParentUID.'"').',    "shadow": false}');
                     //}
                     //else
                     //{
                        $currentParentUID=isset($this->arrBlockToParent[$arrCalExpData[$n][$i][1]])?$this->arrBlockToParent[$arrCalExpData[$n][$i][1]]:$parentUID;
                        array_push($this->Blockly, '{"d":"10","id": "'.$arrCalExpData[$n][$i][1].'",    "opcode": "'.$arrCalExpData[$n][$i][0].'",    "inputs": {},    "fields": {},    "next": null,   "topLevel": '.($currentParentUID==NULL?'true':'false').',"parent":'.($currentParentUID==NULL?'null':'"'.$currentParentUID.'"').',"shadow":false}');// '.($parentUID==NULL?'null':'"'.$parentUID.'"').',    "shadow": false}');
                     //}
                  }
                  else
                  {
                     if(!isset($argInfo['inputs'][1]))//单参数算式
                     {

                        $strFieldsData='';
                        if($arrCalcOptInfo[0]=="operator_mathop")	//这个积木有多种算法参数，需要设置特殊的fields
                        //if($arrCalcOptInfo[1]==2)			//两者作用相同，上一条更具有可读性。
                        {
                           /******************************************************************************************
                              前面$arrArgVal取了$arrCurrentCalcBlock的[2]和[3]，是标准的函数的参数位，
                              如果是单参数函数，只需要取[2]即可，
                              但operator_mathop是最特殊的，它有fields，也有inputs，分别需要取[0]和[2]位。

                              Array
                              (
                                  [0] => ceiling
                                  [1] => ID_tg9@2EhH`neKtbfpd8jJ_DI
                                  [2] => 1.5
                              )

                              motion_movesteps( floor(1.4 )  );

                           ******************************************************************************************/

                           $childBlockParent=isset($this->arrBlockToParent[$thisUID])?$this->arrBlockToParent[$thisUID]:$parentUID;

                           $strFieldsData.='"'.$argInfo["fields"][0][0].'": {"name": "'.$argInfo["fields"][0][0].'","value": "'.trim($arrCurrentCalcBlock[0],'"').'"'. (isset($argInfo["fields"][0][1])?',"variableType": "'.$argInfo["fields"][0][1].'"':'').'}';
                           array_push($this->Blockly, '{"d":"101","id": "'.$thisUID.'","opcode": "'.$arrCalcOptInfo[0].'","inputs": {"'.$argInfo['inputs'][0][0].'": {"name": "'.$argInfo['inputs'][0][0].'","block": "'.$arrArgBlockUID[0].'","shadow": "'.$arrArgShadowUID[0].'"}},"fields": {'.$strFieldsData.'},"next": null,"topLevel": '.($childBlockParent==NULL?'true':'false').',"parent": '.($childBlockParent==NULL?'null':'"'.$childBlockParent.'"').',"shadow": false}');
                        }
                        else
                        {
                           if(isset($argInfo["inputs"][0][0]))
                           {
                              array_push($this->Blockly,'{    "d": "s108",    "id": "'.$arrArgBlockUID[0].'",    "opcode": "'.$argInfo["inputs"][0][1].'",    "inputs": {},    "fields": {        "'.$argInfo["inputs"][0][2].'": {            "name": "'.$argInfo["inputs"][0][2].'",            "value": "'.$arrArgVal[0].'"        }    },    "next": null,    "topLevel": false,    "parent": "'.$thisUID.'",    "shadow": true}');

                              $strInputsData= ' "'.$argInfo["inputs"][0][0].'": {"TAG":"1", "name": "'.$argInfo["inputs"][0][0].'", "block": "'.$arrArgBlockUID[0].'", "shadow":"'.$arrArgBlockUID[0].'"  }';
                           }
                           array_push($this->Blockly, '{"d":"102","id": "'.$thisUID.'","opcode": "'.$arrCalcOptInfo[0].'","inputs": {'.$strInputsData.'},"fields": {'.$strFieldsData.'},"next": null,"topLevel": '.($childBlockParent==NULL?'true':'false').',"parent": "'.$childBlockParent.'","shadow": false}');
                        }

                     }
                     else//双参数算式
                     {//有问题，不支持单步运算，待解决。

                        $childBlockParent=isset($this->arrBlockToParent[$thisUID])?$this->arrBlockToParent[$thisUID]:$parentUID;//原因不明。
                        array_push($this->Blockly, '{"d":"12","id": "'.$thisUID.'","opcode": "'.$arrCalcOptInfo[0].'","inputs": {"'.$argInfo['inputs'][0][0].'": {"name": "'.$argInfo['inputs'][0][0].'","block": "'.$arrArgBlockUID[0].'","shadow": "'.$arrArgShadowUID[0].'"},"'.$argInfo['inputs'][1][0].'": {"name": "'.$argInfo['inputs'][1][0].'","block": "'.$arrArgBlockUID[1].'","shadow": "'.$arrArgShadowUID[1].'"}},"fields": {},"next": null, "topLevel": '.($childBlockParent==NULL?'true':'false').',"parent": '.($childBlockParent==NULL?'null':'"'.$childBlockParent.'"').',"shadow": false}');//"topLevel":false,"parent":null}');//"topLevel":'.($childBlockParent==NULL?'true':'false').',"parent": "'.($childBlockParent==NULL?'null':'"'.$childBlockParent.'"').'","shadow": false}');
                     }
                  }

               }//for $i

               //这个是shadow
               //array_push($this->Blockly,        '{"d":"13","id": "'.$arrChildBlockUID[1].'", "opcode": "'.$arrChildArgBlockInfo[1].'", "inputs": {}, "fields": { "'.$arrChildArgBlockInfo[2].'": { "name": "'.$arrChildArgBlockInfo[2].'", "value": "'.$defaultVAL.'" } }, "next": null, "topLevel": false, "parent": "'.$parentUID.'", "shadow": true}' );

            }//if isset

         }//for $n

         return  Array($childCalcBlockUID,$childCalcBlockUID);	//计算表达式，只要返回顶部积木块的block即可，shadow与block相同。

      }
      else							//如果$arrCalExpData非数组，则它就是一个普通的常量/变量
      {
         $arrCalExpData2=trim($arrCalExpData,'"');


echo "CURRENT SELF DEFINED FUNCTION: ".$this->arrCurrentSDFBlock."\n";

print_r($this->arrSelfDefinedFunctionArgs);
print_r($arrCalExpData);

         if(isset($this->arrVariableUIDS[$arrCalExpData]))//in_array($arrCalExpData,$this->arrVariables))		//对变量直接引用
         {
            //echo "引用变量，需要加默认值积木shadow."; 
            //变量的主积木block，opcode是“data_variable”。
            array_push($this->Blockly, '{"d":"14","id": "'.$arrChildBlockUID[0].'","opcode": "data_variable","inputs": {},"fields": {"VARIABLE": {"name": "VARIABLE","id": "'.$this->arrVariableUIDS[$arrCalExpData].'","value": "'.$arrCalExpData2.'","variableType": ""}},"next": null,"topLevel": false,"parent": "'.$parentUID.'","shadow": false}');
            //默认值积木shadow，opcode从ArgInfo里获取。
//这里不需要shadow了？？？？？
            //array_push($this->Blockly, '{"d":"15","id": "'.$arrChildBlockUID[1].'", "opcode": "'.$arrChildArgBlockInfo[1].'","inputs": {},"fields": {"'.$arrChildArgBlockInfo[2].'": {"name": "'.$arrChildArgBlockInfo[2].'","value": "10"}},"next": null,"topLevel": false,"parent1": null,"shadow": true}');//"parent": "'.$parentUID.'","shadow": true}');
         }
         else if(isset($this->arrSelfDefinedFunctionArgs[$this->arrCurrentSDFBlock][$arrCalExpData]))	//对自制积木中的本地变量直接引用
         {
            //echo "自制积木的变量，需要加shadow.";
            //参数的主机木块block
            array_push($this->Blockly, '{"d":"16","id": "'.$arrChildBlockUID[0].'","opcode": "argument_reporter_string_number","inputs": {},"fields": {"VALUE": {"name": "VALUE","value": "'.$arrCalExpData2.'"}},"next": null,"topLevel": false,"parent": "'.$parentUID.'","shadow": false}');
            //默认值积木shadow//排查下来，不需要了。在S：0的时候已经创建了。
            //array_push($this->Blockly, '{"d":"17","id": "'.$arrChildBlockUID[1].'", "opcode": "'.$arrChildArgBlockInfo[1].'","inputs": {},"fields": {"'.$arrChildArgBlockInfo[2].'": {"name": "'.$arrChildArgBlockInfo[2].'","value": "10"}},"next": null,"topLevel": false,"parent":null,"shadow": true}');//"parent": "'.$parentUID.'","shadow": true}');
         }
         else
         {										//纯数字/字符串
            //echo "纯数字、字符串，不需要补shadow。";
            if($arrCalExpData2==$arrCalExpData)
               $arrCalExpData=$arrCalExpData;//intval($arrCalExpData);
            else
               $arrCalExpData=$arrCalExpData2;

            //print_r($arrChildArgBlockInfo);
            //block
            array_push($this->Blockly, '{"d":"18","id": "'.$arrChildBlockUID[0].'", "opcode": "'.$arrChildArgBlockInfo[1].'","inputs": {},"fields": {"'.$arrChildArgBlockInfo[2].'": {"name": "'.$arrChildArgBlockInfo[2].'","value": "'.$arrCalExpData.'"'.(isset($arrChildArgBlockInfo[3])?' , "variableType": "broadcast_msg"':'').'}},"next": null,"topLevel": false,"parent": "'.$parentUID.'","shadow": true}');
            $arrChildBlockUID[1]=NULL;
         }
         return  $arrChildBlockUID;//Array( 0=>block, 1=>shadow );
      }
   }

   /************************************************************************************************************************
   //检测字符串是否是积木的名称
   ************************************************************************************************************************/
   private function isBlocks($str)
   {
      return isset($this->arrArgumentConfig[$str]);
   }

   /************************************************************************************************************************
   //获取参数名
   ************************************************************************************************************************/
   private function getArgName($opcode)
   {
      return isset($this->arrArgumentConfig[$opcode])?$this->arrArgumentConfig[$opcode]:NULL;
   }
}
?>
