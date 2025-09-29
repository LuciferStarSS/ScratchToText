<?php
set_time_limit(1);
/*
 * Reverse Polish Notation
 * 逆波兰表达式
 *
 * 将四则混合运算字符串转换成逆波兰序列，并计算出结果
 *
 *  
 * 输入：1+2+3+4+5
 * 输出：12+3+4+5
 *
 * 输入：1+2+(3+4)+5  
 * 输出：12+34++5+
 *  
 *





 *  
 */
define( 'DEBUG_L', FALSE);						//调试用

class RPN_LOGIC_EXPRESSION {
   public  $strExpression = '';						//待计算的字符串表达式
   public  $strRPN	  = '';						//生成的逆波兰表达式
   public  $nValue	  = 0;						//表达式计算的结果

   //private $arrMainProcedure = Array();

   private $_priority 	  = Array('('=>0,')'=>0, '||' => 1, '&&' =>2,'!' => 3,'>'=>4,'<'=>4,'=='=>4,'!='=>4 ,

                                  'operator_round' => 5, 'operator_length' => 5, 'sensing_distanceto'=>5,  'sensing_keypressed'=>5, //函数可以直接在这里添加，且只需要添加一次。
                                  'operator_random' => 5, 'sensing_mousedown' =>5,'sensing_answer'=>5,'sensing_touchingobject'=>5,
                                  //'abs'=>5,'ceiling'=>5,'floor'=>5,'sqrt'=>5,'operator_sin'=>5,'cos'=>5,'tan'=>5,'atan'=>5,
                                  //'asin'=>5,'acos'=>5,'ln'=>5,'log'=>5,'e ^'=>5,'10 ^'=>5,//最后两个算法不支持，待研究。
                                  );    	//计算优先级设定

   private $arrExpressions	=Array();

   //类初始化
   //PHP8强制使用此种构造函数
   public function __construct() 
   {
      /*
        由于该类会在多个地方使用，且有时候会十分频繁，所以就不在构造函数里进行相关变量的初始化了。
        每次实例的处理，通过调用init($strExpression);来实现，其中$strExpression为待处理的算术表达式。

        逻辑表达式，肯定需要有>,<,==,!等操作，也就一定能拆分，除非出现异常，
        所以不需要检测是否可以拆分，直接通过init()来完成检测和拆分。			//这里似乎得加强。
      */
   }

   /*************************************************
   *
   *  预处理逻辑表达式
   *
   *  先定位“&&”和“||”，然后从左和右分别定位括号，最终按照括号进行拆分，未拆分部分进行正常的RPN处理
   *  被拆下的数据，进入下一轮预处理；被拆数据原本位置用不会被正则匹配影响的UID替换
   *
   *
   *************************************************/

   function preProcessingSubLogicExpression($strLogicExpression)
   {
      list($strLogicExpression,$arrCalledFunction)=preProcessingFunctionCall(trim($strLogicExpression));

      echo "\n--------------preProcessing-------------------------\n";
      echo "\n".$strLogicExpression."\n";
      print_r($arrCalledFunction);
      echo "\n---------------------------------------\n";
      /******************************************************************************************

          函数调用预处理(preProcessingFunctionCall)
          
             逻辑表达式：
                 1+2<2+4 &&(sensing_mousedown()||sensing_keypressed("c"))&&(sensing_mousedown()||sensing_keypressed("c") || 1>2-3)

             处理后的逻辑表达式（$strLogicExpression）：

                 1+2<2+4 &&( ID_~zDeH$pvELw{Rtfc.5?t_DI || ID_GvP$Z@N:nnQM:j6omMak_DI )&&( ID_ni_k?LnhJ9R4S7#=scac_DI || ID_yJ@ggnsvsy?4$.DYP_;G_DI  || 1>2-3)

             函数调用关系($arrCalledFunction)为：
                 Array
                 (
                     [0] => Array
                         (
                             [0] => sensing_keypressed
                             [1] => ID_yJ@ggnsvsy?4$.DYP_;G_DI
                             [2] => "c"
                         )
                     [1] => Array
                         (
                             [0] => sensing_mousedown
                             [1] => ID_ni_k?LnhJ9R4S7#=scac_DI
                             [2] => 
                         )
                     [2] => Array
                         (
                             [0] => sensing_keypressed
                             [1] => ID_GvP$Z@N:nnQM:j6omMak_DI
                             [2] => "c"
                         )
                     [3] => Array
                         (
                             [0] => sensing_mousedown
                             [1] => ID_~zDeH$pvELw{Rtfc.5?t_DI
                             [2] => 
                         )
                 )

      ********************************************************************************************/

      $arrSubLogicExpression=Array();

      if(strpos($strLogicExpression,'(')===FALSE)			//如果没有括号，就结束
      {
         ////echo "不需要预处理\n";

         //print_r($arrSubLogicExpression);

         return Array(trim($strLogicExpression),NULL,$arrCalledFunction);
      }
      
//先按大小判断拆分
//再按与或非拆分

      $n= preg_match_all("/==|>|<|!=/",$strLogicExpression,$m,PREG_OFFSET_CAPTURE);//
      $nStrLength=strlen($strLogicExpression);

echo "mmmmmmmmmmmmmmmm\n";
      print_r($m);

      for($i=$n-1;$i>=0;$i--)//倒序处理
      {
         ////echo "\n\n\nData:\n";
         //print_r($m[0][$i]);
         ////echo "本轮数据为：$strLogicExpression \n";
         ////echo "本轮数据长为：$nStrLength \n";

         //find right
         $nLeftLoop			=$nRightLoop			=$m[0][$i][1];
         $bLeftParenthesisFound	=$bRightParenthesisFound	=false;
         $nRightLoop+=2;								//||和&&各是两字符，要再跳过一个字符。
         $nLeftLoop-=1;								//||和&&各是两字符，要再跳过一个字符。
         $nLeftParenthesisCount	=$nRightParenthesisCount		=0;
         while($nRightLoop<$nStrLength)
         {
            $chCH=$strLogicExpression[$nRightLoop++];
            ////echo "RIGHT: ".$chCH." $nLeftParenthesisCount \n";
            if($chCH=='(') $nRightParenthesisCount++;
            if($chCH==')') $nRightParenthesisCount--;
            if($nRightParenthesisCount==-1) 
            {
               ////echo "右侧搜索结束\n";
               $bRightParenthesisFound=true;
               break;
            }
         }
         ////echo "右侧搜索到：".$nRightLoop."\n";
         if($bRightParenthesisFound==false) {		//右侧找不到就终止这一轮
            continue;
         }

         //find left
         while($nLeftLoop>=0)
         {
            $chCH=$strLogicExpression[$nLeftLoop--];
            ////echo "LEFT: ".$chCH."\n";
            if($chCH==')') $nLeftParenthesisCount--;
            if($chCH=='(') $nLeftParenthesisCount++;
            if($nLeftParenthesisCount==1) 
            {
               $bLeftParenthesisFound=true;
               break;
            }
         }
         ////echo "左侧搜索到：".$nLeftLoop."\n";

         ////echo "LF: $nLeftParenthesisCount RF:$nRightParenthesisCount \n";
         if($nLeftParenthesisCount==1 && $nRightParenthesisCount==-1)
         {
echo "有效搜索\n";
            $nLeftLoop++;//前面while是先用再递减，所以多减了一次
            $strPrefix=substr($strLogicExpression,0,$nLeftLoop);
            $strSuffix=substr($strLogicExpression,$nRightLoop);

echo "DDDDDDDDDDDDDD: [$strLogicExpression] L: $nLeftLoop  [".$strLogicExpression[$nLeftLoop]."] R: $nRightLoop  [".$strLogicExpression[$nRightLoop-1]."] \n";

            $nLeftLoop++;//为过滤掉“(”，所以又要增一次。

//            $strSubExpression=trim(substr($strLogicExpression,$nLeftLoop,$nRightLoop-$nLeftLoop-1));//长度多减1，去掉后面“)”稳定性存疑。

            $strSubExpression=trim(substr($strLogicExpression,$nLeftLoop,$nRightLoop-$nLeftLoop));//长度多减1，去掉后面“)”稳定性存疑。
//这里一直有问题，不稳定。


echo "SUUUBBBBBBBB: $strSubExpression \n";
            if( preg_match_all("/ID_([^^]*?)_DI/",$strSubExpression)!=1)
            {
               //仅剩下ID_xxxxxxxxxxxxxxxxxxx_DI的话，就不再拆分了。

               $strSubExpUID=UID();
               $arrSubLogicExpression[$strSubExpUID]=$strSubExpression;

               $strLogicExpression=$strPrefix." ".$strSubExpUID." ".$strSuffix;
               $nStrLength=strlen($strLogicExpression);
            }
            //else if(preg_match_all("/+|-|*|\//",$strSubExpression)!=1)
            //{

            //   $strSubExpUID=UID();
            //   $arrSubLogicExpression[$strSubExpUID]=$strSubExpression;

            //   $strLogicExpression=$strPrefix." ".$strSubExpUID." ".$strSuffix;
            //   $nStrLength=strlen($strLogicExpression);
            //}
         }
         ////echo $strSubExpression."\n";
         ////echo "LF: $nLeftParenthesisCount RF:$nRightParenthesisCount \n";
        // //echo "( $nLeftLoop , $nRightLoop )\n";

////echo "=====================================================================\n";
      }

      /******************************************************************************************

         子逻辑表达式拆分

         逻辑表达式为：
             1+2<2+4 &&( ID_~zDeH$pvELw{Rtfc.5?t_DI || ID_GvP$Z@N:nnQM:j6omMak_DI )&&( ID_ni_k?LnhJ9R4S7#=scac_DI || ID_yJ@ggnsvsy?4$.DYP_;G_DI  || 1>2-3)

         拆分后的主逻辑表达式：
             1+2<2+4 && ID_RBcGN_JoYJP=.D}hB]S2_DI && ID_P15U0:`f2akND[gk~2n`_DI

         子逻辑表达式
             Array
             (
                 [ID_P15U0:`f2akND[gk~2n`_DI] =>  ID_ni_k?LnhJ9R4S7#=scac_DI || ID_yJ@ggnsvsy?4$.DYP_;G_DI  || 1>2-3
                 [ID_RBcGN_JoYJP=.D}hB]S2_DI] =>  ID_~zDeH$pvELw{Rtfc.5?t_DI || ID_GvP$Z@N:nnQM:j6omMak_DI 
             )

      ******************************************************************************************/

      //var_dump($m);
     ////echo "1最终表达式为：".$strLogicExpression."\n";
      //var_dump($arrSubLogicExpression);


      $n= preg_match_all("/&&|\|\|/",$strLogicExpression,$m,PREG_OFFSET_CAPTURE);
      $nStrLength=strlen($strLogicExpression);

     // print_r($m);



      for($i=$n-1;$i>=0;$i--)//倒序处理
      {
         ////echo "dddddddd  $i  dddddddddddd\n";
         ////echo "\n\n\nData:\n";
         //print_r($m[0][$i]);
         ////echo "本轮数据为：$strLogicExpression \n";
         ////echo "本轮数据长为：$nStrLength \n";

         //find right
         $nLeftLoop			=$nRightLoop			=$m[0][$i][1];
         $bLeftParenthesisFound	=$bRightParenthesisFound	=false;
         $nRightLoop+=2;								//||和&&各是两字符，要再跳过一个字符。
         $nLeftLoop-=1;								//||和&&各是两字符，要再跳过一个字符。
         $nLeftParenthesisCount	=$nRightParenthesisCount		=0;

         $bLogicExpressionFound=false;

         while($nRightLoop<$nStrLength)
         {
            $chCH=$strLogicExpression[$nRightLoop++];
            ////echo "RIGHT: ".$chCH." $nLeftParenthesisCount \n";
            if($chCH=='(') $nRightParenthesisCount++;
            else if($chCH==')') $nRightParenthesisCount--;
            else if($chCH=='|' || $chCH=='&')  $bLogicExpressionFound=true;	//出现了&和|，表示正常的&&和||被截断了，就终止这次处理，进入下一个匹配的地方。

            if($nRightParenthesisCount==-1) 
            {
               ////echo "右侧搜索结束\n";
               $bRightParenthesisFound=true;
               break;
            }
         }

         ////echo "右侧搜索到：".$nRightLoop."\n";


         if($bLogicExpressionFound) continue;

         if($bRightParenthesisFound==false) {		//右侧找不到就终止这一轮
            continue;
         }

         //find left
         while($nLeftLoop>=0)
         {
            $chCH=$strLogicExpression[$nLeftLoop--];
            ////echo "LEFT: ".$chCH."\n";
            if($chCH==')') $nLeftParenthesisCount--;
            else if($chCH=='(') $nLeftParenthesisCount++;
            else if($chCH=='|' || $chCH=='&')  $bLogicExpressionFound=true;

            if($nLeftParenthesisCount==1) 
            {
               $bLeftParenthesisFound=true;
               break;
            }
         }
         ////echo "左侧搜索到：".$nLeftLoop."\n";


         if($bLogicExpressionFound) continue;

         if($nLeftParenthesisCount==1 && $nRightParenthesisCount==-1)
         {

//////echo "READY\n";
            $nLeftLoop++;//前面while是先用再递减，所以多减了一次
            $strPrefix=substr($strLogicExpression,0,$nLeftLoop);
            $strSuffix=substr($strLogicExpression,$nRightLoop);
            $nLeftLoop++;//为过滤掉“(”，所以又要增一次。

            $strSubExpression=trim(substr($strLogicExpression,$nLeftLoop,$nRightLoop-$nLeftLoop-1));//长度多减1，去掉后面“)”
////echo " $strPrefix | $strSubExpression |  $strSuffix\n";

//var_dump(preg_match_all("/ID_([^^]*?)_DI/",$strSubExpression,$x));
/*
            if( preg_match_all("/ID_([^^]*?)_DI/",$strSubExpression,$x)!=1)
            {
               //echo "仅剩下ID_xxxxxxxxxxxxxxxxxxx_DI的话，就不再拆分了。";

               $strSubExpUID=UID();
               $arrSubLogicExpression[$strSubExpUID]=$strSubExpression;

               $strLogicExpression=$strPrefix." ".$strSubExpUID." ".$strSuffix;

//echo PHP_EOL.$strLogicExpression.PHP_EOL;
               $nStrLength=strlen($strLogicExpression);
            }
            else  if(preg_match_all("/\+|\-|\*|\/|\%/",$strSubExpression,$x)!=1)
            {
//echo "+-* /\n";
               ////echo "-------- $strSubExpression ------\n";
               $strSubExpUID=UID();
               $arrSubLogicExpression[$strSubExpUID]=$strSubExpression;

               $strLogicExpression=$strPrefix." ".$strSubExpUID." ".$strSuffix;
               $nStrLength=strlen($strLogicExpression);
               ////echo "遇到IDDI了:\n";
               ////echo $strSubExpression."\n";
               ////echo "NEW expression: $strPrefix $strSubExpUID $strSuffix \n ";
            }
            else
            {
*/
               ////echo "-------- $strSubExpression ------\n";
               $strSubExpUID=UID();
               $arrSubLogicExpression[$strSubExpUID]=$strSubExpression;

               $strLogicExpression=$strPrefix." ".$strSubExpUID." ".$strSuffix;
               $nStrLength=strlen($strLogicExpression);
               ////echo "遇到IDDI了:\n";
               ////echo $strSubExpression."\n";
               ////echo "NEW expression: $strPrefix $strSubExpUID $strSuffix \n ";

           // }
////echo " $i : $strSubExpression \n";

         }
         ////echo $strSubExpression."\n";
         ////echo "LF: $nLeftParenthesisCount RF:$nRightParenthesisCount \n";
         ////echo "( $nLeftLoop , $nRightLoop )\n";
         ////echo "dddddddddddddddddddd $n ddddddddddddddd\n";
      }


      ////echo "\n---------------------------------------\n";
      ////echo "2最终表达式为：".$strLogicExpression."\n";
      //var_dump($arrSubLogicExpression);
      ////echo "\n---------------------------------------\n";

      $strLogicExpression=trim($strLogicExpression);
      if(isset($arrSubLogicExpression[$strLogicExpression]))
      {
////echo "replace.";
          $strTemp=$strLogicExpression;
          $strLogicExpression=$arrSubLogicExpression[$strLogicExpression];
          unset($arrSubLogicExpression[$strTemp]);
      }

      //var_dump($m);
      ////echo "\n---------------------------------------\n";
      ////echo "2最终表达式为：".$strLogicExpression."\n";
      //var_dump($arrSubLogicExpression);
      ////echo "\n---------------------------------------\n";
      return Array($strLogicExpression,$arrSubLogicExpression,$arrCalledFunction);
   }

   /*******************************************
   *
   *
   *   $arrData		传入的数据  Array( 0=> strExpression, 1=>Array())
   *
   *   $key		最后一条运算符操作需要修改的UID值
   *
   *   最终数据的第一组的最后一个的UID是最底层的UID
   *
   *******************************************/
   public function build($strLogicExpression,$arrSubLogicExpression)
   {
      /******************************************************************************************

         至此，原始数据（1+2<2+4 &&(sensing_mousedown()||sensing_keypressed("c"))&&(sensing_mousedown()||sensing_keypressed("c") || 1>2-3)）已经被拆解成

             1.函数调用关系($arrCalledFunction)为：
                 Array
                 (
                     [0] => Array
                         (
                             [0] => sensing_keypressed
                             [1] => ID_yJ@ggnsvsy?4$.DYP_;G_DI
                             [2] => "c"
                         )
                     [1] => Array
                         (
                             [0] => sensing_mousedown
                             [1] => ID_ni_k?LnhJ9R4S7#=scac_DI
                             [2] => 
                         )
                     [2] => Array
                         (
                             [0] => sensing_keypressed
                             [1] => ID_GvP$Z@N:nnQM:j6omMak_DI
                             [2] => "c"
                         )
                     [3] => Array
                         (
                             [0] => sensing_mousedown
                             [1] => ID_~zDeH$pvELw{Rtfc.5?t_DI
                             [2] => 
                         )
                 )

             2.拆分后的主逻辑表达式($strLogicExpression)：
                 1+2<2+4 && ID_RBcGN_JoYJP=.D}hB]S2_DI && ID_P15U0:`f2akND[gk~2n`_DI

             3.子逻辑表达式($arrSubLogicExpression):
                 Array
                 (
                     [ID_P15U0:`f2akND[gk~2n`_DI] =>  ID_ni_k?LnhJ9R4S7#=scac_DI || ID_yJ@ggnsvsy?4$.DYP_;G_DI  || 1>2-3
                     [ID_RBcGN_JoYJP=.D}hB]S2_DI] =>  ID_~zDeH$pvELw{Rtfc.5?t_DI || ID_GvP$Z@N:nnQM:j6omMak_DI 
                 )
          接下来，就是将2和3，根据逻辑运算进行拆分。

      ******************************************************************************************/


echo "++++++++++++++++++++++++++++++>\n";
print_r($strLogicExpression);
echo "<++++++++++++++++++++++++++++++\n";
      $arrResult=Array();

      //对主逻辑表达式进行处理
      $arrSplittedExpression = preg_split("/(\&\&)|(\|\|)|(>)|(<)|(==)|(!=)|(!)/",$strLogicExpression,-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);//将字符串表达式转成数组
      $arrData=$this->exp2RPN($arrSplittedExpression);					//转换为逆波兰表达式
      $arrResult[0]=$this->toScratchJSON($arrData);

      /******************************************************************************************

          先对拆分后的主逻辑表达式（1+2<2+4 && ID_RBcGN_JoYJP=.D}hB]S2_DI && ID_P15U0:`f2akND[gk~2n`_DI）执行exp2RPN()操作，得到：
              Array
              (
                  [0] => 1+2
                  [1] => 2+4
                  [2] => <
                  [3] => ID_RBcGN_JoYJP=.D}hB]S2_DI
                  [4] => &&
                  [5] => ID_P15U0:`f2akND[gk~2n`_DI
                  [6] => &&
              )

           然后用toScratchJSON处理为：
              Array
              (
                  [0] => Array
                      (
                          [0] => <
                          [1] => ID_Mx8SpG9t~OzUm2.sKMaZ_DI
                          [2] => 1+2
                          [3] => 2+4
                      )
                  [1] =>  Array
                      ( 
                          [0] => &&
                          [1] => ID_O1wroZdI}`o=.si78Kzr_DI
                          [2] => ID_Mx8SpG9t~OzUm2.sKMaZ_DI
                          [3] => ID_RBcGN_JoYJP=.D}hB]S2_DI
                      )
                  [2] =>  Array
                      (
                          [0] => &&
                          [1] => ID_N2pKKkC~Z2_~1UdU9qPA_DI
                          [2] => ID_O1wroZdI}`o=.si78Kzr_DI
                          [3] => ID_P15U0:`f2akND[gk~2n`_DI
                      )
              )

            得到的结果存入$arrResult[0]处。

      ******************************************************************************************/

      if($arrSubLogicExpression!=NULL){
         foreach($arrSubLogicExpression as $key=>$value)					//对子逻辑表达式进行处理
         {

            /******************************************************************************************

            对每个子逻辑表达式，也执行相同的处理，数据存入$arrResult[1]中。
            例如：
                $arrSubLogicExpression['ID_P15U0:`f2akND[gk~2n`_DI']

            其值为：
                ID_ni_k?LnhJ9R4S7#=scac_DI || ID_yJ@ggnsvsy?4$.DYP_;G_DI  || 1>2-3

            执行exp2RPN后，得到：
                Array
                (
                    [0] => ID_ni_k?LnhJ9R4S7#=scac_DI
                    [1] => ID_yJ@ggnsvsy?4$.DYP_;G_DI
                    [2] => ||
                    [3] => 1
                    [4] => 2-3
                    [5] => >
                    [6] => ||
                )

            经toScratchJSON处理后，得到：
                Array
                (
                    [0] => Array
                        (
                            [0] => ||
                            [1] => ID_F_F1bxSktQ60epv6s[r9_DI
                            [2] => ID_ni_k?LnhJ9R4S7#=scac_DI
                            [3] => ID_yJ@ggnsvsy?4$.DYP_;G_DI
                        )
                    [1] => Array
                        (
                            [0] => >
                            [1] => ID_2q{q[pPHjd8Xjyj1_@mi_DI
                            [2] => 1
                            [3] => 2-3
                        )
                    [2] => Array
                        (
                            [0] => ||
                            [1] => ID_hjv9513~h_]2kTcpsZaa_DI
                            [2] => ID_F_F1bxSktQ60epv6s[r9_DI
                            [3] => ID_2q{q[pPHjd8Xjyj1_@mi_DI
                        )
                )
            其中，
                 [2]为三个逻辑运算积木的最底层的一块，它的[2][1]为积木的UID，此UID数据由toScratchJSON自动生成，
                 为了保证这三组数据与主逻辑表达式相关联，因此该UID必须改为“ID_P15U0:`f2akND[gk~2n`_DI”。

            最终得到的结果，需要装入$arrResult[1]里。

            ******************************************************************************************/

            $arrSplittedExpression = preg_split("/(\&\&)|(\|\|)|(>)|(<)|(==)|(!=)|(!)/",$value,-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);//将字符串表达式转成数组
            $arrData=$this->exp2RPN($arrSplittedExpression);					//转换为逆波兰表达式
            $arrResult2=$this->toScratchJSON($arrData);
            $nResult2Length=count($arrResult2);

            $arrResult2[$nResult2Length-1][1]=$key;			//在子逻辑表达式被解析后，原有的UID关系会被重置，此时，需要将最后一组的UID改为当前的$key。

            foreach($arrResult2 as $key=>$value)				//追加装配数据到$arrResult[1]中
            {
               $arrResult[1][]=$value;
            }
         }
      }
      
      echo "~~~~~~~~~~build~~~~~~~最终结果~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~\n";
      print_r($arrResult);

      return $arrResult;
   }

   //初始化数据
   //RPN初始化后，可以多次调用，所以表达式的初始化，没有放在类初始化里。
   public function init($strLogicExpression)
   {
      //针对括号进行预处理
      list($strLogicExpression,$arrSubLogicExpression,$arrCalledFunction)=$this->preProcessingSubLogicExpression($strLogicExpression);

////echo '$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$'.PHP_EOL;
////echo "preProcessingSubLogicExpression:\n";
//var_dump($arrCalledFunction);
//var_dump($strLogicExpression);
//var_dump($arrSubLogicExpression);
////echo "以上拆分并没有问题。 strLogicExpression（ $strLogicExpression ）是所有积木的最底层数据。".PHP_EOL;


      //如果只有一组子串，
      //将子串数据作为$strLogicExpression，然后$arrSubLogicExpression为NULL
      if($arrSubLogicExpression!=NULL && count($arrSubLogicExpression)==1)// && $arrSubLogicExpression[0][1]==$strLogicExpression)		//处理后的字符串跟函数调用的UID相同
      {
         /****************************************************************************
             如果表达式整个都被()包围，则会出现整个表达式都被划分为子逻辑串，
             例如：
                 (  ( operator_random( operator_random( 4 , 10 )  , 100 )  > 1 )  &&  ( operator_random( operator_random( 4 , 10 )  , 100 )  > 1 )  ) 

             经过函数调用和子逻辑字符串处理后：
                 ID_h113zRFIMLp`A6mPVG1G_DI 
             子逻辑字符串数据为：

                  Array(1) 
                  (
                     ["ID_h113zRFIMLp`A6mPVG1G_DI"] => ( ID_M]_S@O2HoxwJpgm`bYxA_DI > 1)&&( ID_@4Y5#9D2_lVD1{pA$1OT_DI > 1)
                  )

             此时，要把该子逻辑字符串设为主逻辑字符串，并清掉子逻辑字符串数据。
             当前算法暂时只能脱掉一层无意义的括号，这层括号，是由s2c.class.php算法生成的。

         ****************************************************************************/

         //$key=array_keys($arrSubLogicExpression);
         //if($key[0]==$strLogicExpression)
         if(isset($arrSubLogicExpression[$strLogicExpression]))
         {
            $strLogicExpression=$arrSubLogicExpression[$strLogicExpression];
            $arrSubLogicExpression=NULL;
         }
      }


      if(count($arrCalledFunction)==1 && $arrCalledFunction[0][1]==$strLogicExpression)		//处理后的字符串跟函数调用的UID相同
      {
          /****************************************************************************
              这里只有一个被预处理的函数，也就不存在子逻辑运算字符串

              例如：
                    sensing_mousedown()

              输出：
                    Array
                    (
                        [0] => Array						//[0]：主逻辑表达式积木块
                            (
                                [0] => Array
                                    (
                                        [0] => sensing_mousedown
                                        [1] => ID_[=V;Uwz{Q~JENiSxvivP_DI
                                        [2] => 
                                    )
                            )
                    )
          ****************************************************************************/

          $arrResult[2]=$arrCalledFunction;
          return $arrResult;
      }

//print_r($strLogicExpression);
//print_r($arrSubLogicExpression);

      $arrResult=$this->build($strLogicExpression,$arrSubLogicExpression);

//print_r($arrResult);
      //将函数调用追加到$arrResult[2]中。
      $nCalledFunctionLength=count($arrCalledFunction);		//倒序插入，这样每个被拆分的preAttach，都能准确地找到parentUID
      for($i=$nCalledFunctionLength-1;$i>=0;$i--)
      {
         $arrResult[2][]=$arrCalledFunction[$i];
      }
      /********************************************************

         将函数调用关系数据追加到$arrResult[1]后，整个对逻辑字符串的转换算法就结束了。
         最终，对字符串（1+2<2+4 &&(sensing_mousedown()||sensing_keypressed("c"))&&(sensing_mousedown()||sensing_keypressed("c") || 1>2-3)）
         进行处理后得到的结果为：

             Array
             (
                [0] => Array						//这里为主逻辑表达式拆分后的结果
                    (
                       [0] => Array
                           (
                               [0] => <
                               [1] => ID_Mx8SpG9t~OzUm2.sKMaZ_DI
                               [2] => 1+2
                               [3] => 2+4
                           )
                       [1] => Array
                           (
                               [0] => &&
                               [1] => ID_O1wroZdI}`o=.si78Kzr_DI
                               [2] => ID_Mx8SpG9t~OzUm2.sKMaZ_DI
                               [3] => ID_RBcGN_JoYJP=.D}hB]S2_DI
                           )
                       [2] => Array
                           (
                               [0] => &&
                               [1] => ID_N2pKKkC~Z2_~1UdU9qPA_DI	//这个是所有积木的最底层UID
                               [2] => ID_O1wroZdI}`o=.si78Kzr_DI
                               [3] => ID_P15U0:`f2akND[gk~2n`_DI
                           )
                    )
                [1] => Array						//这里为子逻辑表达式拆分后的结果
                    (
                        [0] => Array
                            (
                                [0] => ||
                                [1] => ID_F_F1bxSktQ60epv6s[r9_DI
                                [2] => ID_ni_k?LnhJ9R4S7#=scac_DI
                                [3] => ID_yJ@ggnsvsy?4$.DYP_;G_DI
                            )
                        [1] => Array
                            (
                                [0] => >
                                [1] => ID_2q{q[pPHjd8Xjyj1_@mi_DI
                                [2] => 1
                                [3] => 2-3
                            )
                        [2] => Array
                            (
                                [0] => ||
                                [1] => ID_P15U0:`f2akND[gk~2n`_DI
                                [2] => ID_F_F1bxSktQ60epv6s[r9_DI
                                [3] => ID_2q{q[pPHjd8Xjyj1_@mi_DI
                            )
                        [3] => Array
                            (
                                [0] => ||
                                [1] => ID_RBcGN_JoYJP=.D}hB]S2_DI
                                [2] => ID_~zDeH$pvELw{Rtfc.5?t_DI
                                [3] => ID_GvP$Z@N:nnQM:j6omMak_DI
                            )
                    )
                [2] => Array						//这里为函数调用
                    (
                        [0] => Array
                            (
                                [0] => sensing_mousedown
                                [1] => ID_~zDeH$pvELw{Rtfc.5?t_DI
                                [2] => 
                            )
                        [1] => Array
                            (
                                [0] => sensing_keypressed
                                [1] => ID_GvP$Z@N:nnQM:j6omMak_DI
                                [2] => "c"
                            )
                        [2] => Array
                            (
                                [0] => sensing_mousedown
                                [1] => ID_ni_k?LnhJ9R4S7#=scac_DI
                                [2] => 
                            )
                        [3] => Array
                            (
                                [0] => sensing_keypressed
                                [1] => ID_yJ@ggnsvsy?4$.DYP_;G_DI
                                [2] => "c"
                            )
                    )
             )

      ********************************************************/

      ////echo "\n===========================最终结果======================\n";
      //print_r($arrResult);

      return $arrResult;
   }

   /********************************************************
   *  清理掉多余的括号
   *
   *      下列表达式中的括号要清理掉
   *         (a)  (1+2)   ( ID_xxxxxxxxxxxxxxxxxxxx_DI )
   *
   *      下列表达式中的括号不能被清理
   *         a+(b+c)  (1+2)*3    (1+3)*(3+2)
   *
   *      下列表达式中需要清理掉外层所有无用括号
   *         ((1+3)-(4-2))   ((((1+3)-(4-2)) ) )
   *
   *
   ********************************************************/
   private function removeUselessParentheses($expression) {
      $expression = trim($expression);
      $length = strlen($expression);
    
      // 检查表达式是否被括号包围
      $isWrapped = false;
      if ($expression[0] === '(' && $expression[$length - 1] === ')') {
         $isWrapped = true;
      }
    
      // 如果表达式没有被括号包围，直接返回
      if (!$isWrapped) {
         return $expression;
      }
    
      // 检查括号是否匹配且内部表达式是独立的
      $stack = [];
      $isIndependent = true;
    
      for ($i = 0; $i < $length; $i++) {
         $char = $expression[$i];
        
         if ($char === '(') {
            array_push($stack, $i);
         } elseif ($char === ')') {
            if (empty($stack)) {
                $isIndependent = false;
                break;
            }
            array_pop($stack);
            
            // 如果括号不在最外层且有运算符在括号外，则不是独立表达式
            if (empty($stack) && $i < $length - 1) {
                $nextChar = $expression[$i + 1];
                if (!in_array($nextChar, ['+', '-', '*', '/', ')', ' '])) {
                    $isIndependent = false;
                }
            }
         } else if (empty($stack)) {
            // 如果在最外层遇到运算符，则不是独立表达式
            if (in_array($char, ['+', '-', '*', '/'])) {
                $isIndependent = false;
                break;
            }
         }
      }
    
      // 如果栈不为空，说明括号不匹配
      if (!empty($stack)) {
         $isIndependent = false;
      }
    
      // 如果是独立表达式，移除外层括号并递归处理
      if ($isIndependent) {
         $innerExpression = substr($expression, 1, -1);
         return $this->removeUselessParentheses($innerExpression);
      }
    
      // 如果不是独立表达式，处理内部可能的嵌套括号
      $result = '';
      $stack = [];
      $inParentheses = false;
    
      for ($i = 0; $i < $length; $i++) {
         $char = $expression[$i];
        
         if ($char === '(') {
            if (empty($stack)) {
                $inParentheses = true;
                $start = $i;
            }
            array_push($stack, $i);
         } elseif ($char === ')') {
            if (empty($stack)) {
                $result .= $char;
                continue;
            }
            array_pop($stack);
            
            if (empty($stack)) {
                $inParentheses = false;
                $subExpression = substr($expression, $start, $i - $start + 1);
                $result .= $this->removeUselessParentheses($subExpression);
            }
         } elseif (empty($stack)) {
            $result .= $char;
         }
      }
    
      return $result;

   }

   /********************************************************
   *
   *   检测字符串中左右括号是否匹配
   *
   *   返回：
   *         =0：无括号/括号数正常
   *         >0：左括号多的数量
   *         <0：右括号多的数量
   *
   *   当逻辑表达式被表示成“(A>B)”/“(A==B)”/“(A<B)”时，
   *   在对逻辑判断进行拆分后，左右两个变量就会变成“(A”和“B)”，
   *   此时，需要清理。
   *
   ********************************************************/
   private function checkParenthesis($strData)
   {
      if(strpos($strData,'(')===false && strpos($strData,')')===false) return $strData;	//字符串中没有括号，不需要处理。
      $nLeft=$nRight=0;

      /**************************************************************************************

         checkParenthesis2算法:			//正则也可以统计，但实际测试下来，速度不占优势

             $t1=microtime(true);
             $nLeft2=preg_match_all("/\(/",$strData,$m);
             $nRight2=preg_match_all("/\)/",$strData,$m);
             $t2=microtime(true);
             //echo __FUNCTION__."2 $strData $nLeft2 $nRight2  ".($t2-$t1)."\n";


         checkParenthesis算法:

             $t1=microtime(true);
             for($i=0;$i<strlen($strData);$i++)
             {
                if($strData[$i]=='(') $nLeft++;
                else if($strData[$i]==')') $nRight++;
             }
             $t2=microtime(true);
             //echo __FUNCTION__." $strData $nLeft $nRight ".($t2-$t1)."\n";


         benchmark:

             checkParenthesis2 ( ID_yn@[`M~[IOLV`?POF6rO_DI  1 0  9.5367431640625E-7
             checkParenthesis ( ID_yn@[`M~[IOLV`?POF6rO_DI  1 0 1.9073486328125E-6
             checkParenthesis2  1) 0 1  9.5367431640625E-7
             checkParenthesis  1) 0 1 9.5367431640625E-7
             checkParenthesis2 ( ID_]Qx@gYEBAiCbsEj~Bal6_DI  1 0  9.5367431640625E-7
             checkParenthesis ( ID_]Qx@gYEBAiCbsEj~Bal6_DI  1 0 1.9073486328125E-6
             checkParenthesis2  1) 0 1  0
             checkParenthesis  1) 0 1 0

      **************************************************************************************/

      //$t1=microtime(true);
      for($i=0;$i<strlen($strData);$i++)
      {
         if($strData[$i]=='(') $nLeft++;
         if($strData[$i]==')') $nRight++;
      }
      //$t2=microtime(true);
      ////echo __FUNCTION__." $strData $nLeft $nRight ".($t2-$t1)."\n";

      $nTrimParenthesis=$nLeft-$nRight;				//>0 左括号多了   <0  右括号多了

      if($nTrimParenthesis>0)					//左括号多了
         return trim(substr($strData,$nTrimParenthesis));       			//$nTrimParenthesis为几，就从几开始截断字符串。这里利用了substr的第二和第三个参数的功能。
      else if($nTrimParenthesis<0)				//右括号多了
         return trim(substr($strData,0,strlen($strData)+$nTrimParenthesis));		//$nTrimParenthesis为负，也就等于从末尾去掉$nTrimParenthesis个字符。
      return trim($strData);					//括号匹配，原字符串没有问题。
   }


   //对已拆分数组进行逆波兰处理
   private function exp2RPN($arrSplittedExpression) 
   {
      $_stack = Array('#');
      $_rpnexp=Array();
      $nCheckParenthesis=0;					//用于检测左右括号是否对应
      $len = count($arrSplittedExpression);				//字符串表达式已经转成了数组

      //var_dump($arrSplittedExpression);

      for($i = 0; $i < $len; $i++)				//遍历数组
      {
         if(DEBUG_L)
         {
            //echo "\nLOOP: ".$i. "\t ORDER:". count($_rpnexp)."\n";
            //echo "\n\n++++++++++++STACK+++++++++++:\t";
            print_r($_stack);
            //echo "\n\nRPN:\t";print_r($_rpnexp);
         }

         $str = trim($this->removeUselessParentheses($arrSplittedExpression[$i]));				//清理空格
         //$str = trim($arrSplittedExpression[$i]);				//清理空格

         ////echo " $i :\t".$str."\n";
         if ($str == '(')						//括号优先级最高，先检测是否有左括号出现
         {
            $nCheckParenthesis++;						//遇到左括号，加1；遇到右括号，减1
            $_stack[] = $this->removeUselessParentheses($str);						//将左括号压入运算符号堆栈
            ////echo "将括号）压入堆栈\r\n";

            continue;								//立刻进入下一次循环
         } 
         else if ( !isset($this->_priority[$str]) )// !in_array($str, $this->_operator)) 			//非已定义的运算符号，即为操作数/变量
         {
            ////echo "其它数据: $str\r\n";
            if($str!='')						//屏蔽掉空数据
               $_rpnexp[] = $this->checkParenthesis($str);		//清除多余的括号。	//放入输出结果数组中
            continue;								//立刻进入下一次循环
         }
         else if ($str == ')')						//右括号出现，表示有一个完整的括号结束了
         {
            $nCheckParenthesis--;						//遇到左括号，加1；遇到右括号，减1
            ////echo "括号结束\n";
            //print_r($_stack);
            ////echo "堆栈情况↑\r\n";

            for($j = count($_stack); $j >= 0; $j--)			//倒序检测运算符堆栈，把这一对括号中的操作都输出
            {
               $tmp = array_pop($_stack);						//取出堆栈顶的数据
               ////echo "[".$tmp."]\r\n";
               if ($tmp == '(') { break;}							//直到处理完当前的整个括号内数据
               else $_rpnexp[] = $tmp;						//需要将该数据放入输出结果数组中
               ////echo "tmp:".$tmp."\r\n";

            }

            //print_r($_stack);
            ////echo "处理结束\r\n";

            continue;								//立刻进入下一次循环
         }
         else if (isset($this->_priority[end($_stack)]) && $this->_priority[$str] <= $this->_priority[end($_stack)]) //非括号内，非操作数，即为“+、-、*、/”四个操作，需要判断优先级
         {								  	//当前操作优先级比堆栈中最后一个的操作低，则需要处理减法问题
            ////echo DEBUG_L?"优先级变化:当前操作：$str\t上一个操作：".end($_stack)."\n":'';
            ////echo "优先级改变时，数据处理情况↓\r\n";

            $_rpnexp[] = array_pop($_stack);			//这个操作，无论“+、-、*、/”，都要追加到结果数组中

            if(count($_stack)>1)        // && ($str=='||'))		//($str=='-'|| $str=='+') 即  $this->_priority[$str]==2
            {									//当$_stack中已经寄存有未处理的操作符，而当前又出现权限较低的+和-时
               $val1=array_pop($_stack);					//获取$_stack堆栈顶部数据
               if($val1=='&&')							//当堆栈中积存的是“-”，则需要先算这个减法
               {
                  $_rpnexp[]=$val1;
               }
               else
                 array_push($_stack,$val1);				//否则把该操作符放回去
            }

            $_stack[] = $str;						//将当前运算符压入堆栈

           ////echo "优先级改变时，数据处理情况↓\r\n";

            continue;								//立刻进入下一次循环
         } 
         else								//当前操作优先级高
         {
            if($str!='')
               $_stack[] = $str;						//直接将操作符压入堆栈
            continue;								//直接进入下一次循环
         }
      }

      for($i = count($_stack); $i >= 0; $i--)			//检测堆栈中是否有遗漏的操作
      {
         if (end($_stack) == '#') break;					//检测到达底部，结束
         $_rpnexp[] = array_pop($_stack);				//直接追加到结果数组
      }

      //var_dump($arrCalledFunction);
      ////echo "result\r\n";

      if( $nCheckParenthesis!=0 ) $_rpnexp=  FALSE;
      ////echo "[[[[[[[[[[[[[[[[[[[ _rpnexp ]]]]]]]]]]]]]]]]]]]\n";
      //var_dump($_rpnexp);
      return $_rpnexp;						//如果输入数据有误（比如括号不匹配，连续多个运算符叠加的情况暂时没有处理），就返回FALSE；否则返回包含逆波兰表达式数据的数组
   }


   /********************************************
       将经RPN算法整理好的数据，以Scratch3.0积木支持的JSON数据格式输出。
   ********************************************/
   public function toScratchJSON($_rpnexp)
   {
      $jsonArr=Array();
      $data=Array();
      $type	= Array('>','<','==','&&','||',"!=","!");				//限定了只能处理这四种运算
      if($_rpnexp===FALSE) return NULL;

      for($i=0;$i<count($_rpnexp);$i++)
      {
         ////echo "data:\r\n";
         //print_r($data);
         if(  $_rpnexp[$i]!=NULL)//数据莫名其妙地多了一个NULL，暂时屏蔽。
         {
            ////echo "------------------------>>>>\n";
            //            var_dump($_rpnexp[$i]);
            ////echo "<<<<<<<<------------------------\n";
            if( !isset($this->_priority[$_rpnexp[$i]]))  //!in_array($_rpnexp[$i],$type))		//非计算符号，则认定为数字/变量
            {
               if(!is_numeric($_rpnexp[$i])) $bFormula=TRUE;
               array_unshift($data,$_rpnexp[$i]);	//将数据(数字/变量)插入到数组$data的开头
               //array_unshift($data,$_rpnexp[$i]);	//将数据(数字/变量)插入到数组$data的开头
            }
            else							//处理“+,-,*,/”
            {
               if(isset($this->_priority[$_rpnexp[$i]]) && $this->_priority[$_rpnexp[$i]]==5 )  // $_rpnexp[$i]=="operator_round" || $_rpnexp[$i]=="operator_length"  || $_rpnexp[$i]=="sensing_distanceto")
               {
                  $val1=array_shift($data);				//获取数组$data的第一个数据，并删除

                  $uid=UID();
                  $jsonArr[]=Array($_rpnexp[$i], $uid,$val1,0);  //opcode,uid,arg1,为保持一致，加一个空数据
                  array_unshift($data,$uid);
               }
               else{
                  $val1=array_shift($data);				//获取数组$data的第一个数据，并删除
                  $val2=array_shift($data);				//获取数组$data的第一个数据，并删除

                  //当逻辑算式有误时，此处不能正确判断缺了谁。比如“1>”和“<2”，1和2都会在$val1中。
                  $uid=UID();
                  if($_rpnexp[$i]=='!')				//单目操作
                  {
                     array_unshift($data,$val2);				//推入已经弹出的数据
                     $jsonArr[]=Array($_rpnexp[$i], $uid, $val1);  	//opcode,uid,arg1
                  }
                  else							//双目操作
                  {
                     if($val2=="") $val2="错误：表达式不完整。";
                     $jsonArr[]=Array($_rpnexp[$i], $uid, $val2, $val1);  //opcode,uid,arg1,arg2
                  }
                  switch($_rpnexp[$i])
                  {
                  case '>':
                     array_unshift($data,$uid);			//将计算结果UID保存到数组$data的开头
                     break;
                  case '<':
                     array_unshift($data,$uid);
                     break;
                  case '==':
                     array_unshift($data,$uid);
                     break;
                  case '!=':
                     array_unshift($data,$uid);
                     break;
                  case '&&':
                     array_unshift($data,$uid);
                     break;
                  case '||':
                     array_unshift($data,$uid);
                     break;					//所以这里的default可以安心地去掉。
                  case '!':
                     array_unshift($data,$uid);
                     break;					//所以这里的default可以安心地去掉。
                  //default:					//由于前面if里的in_array()已经过滤了非“+,-,*,/”的情况，
                  }
               }
            }
         }
      }

      if(DEBUG_L)
      {
         print_r($data);
         //echo "jsonArr\n";
         print_r($jsonArr);
      }
      return $jsonArr;
   }

};
//RPN类定义结束
?>
