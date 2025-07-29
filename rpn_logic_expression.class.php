<?php
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

   private $arrMainProcedure = Array();
   private $_expression   = Array();					//拆分为数组的计算表达式
   private $_rpnexp 	  = Array();					//队列，用于存放逆波兰表达式
   private $_stack 	  = Array('#');					//堆栈，用于存放操作符
   private $_priority 	  = Array('||' => 1, '&&' =>2,'>'=>4,'<'=>4,'=='=>4,'!='=>4 ,'!' => 3);    	//计算优先级设定
   private $_operator 	  = Array('(', '&&', '||', '!',  ')','>','<','==','!=');	//四则混合运算中的操作符
   private $arrExpressions	=Array();
   //private $soup2 = '!#%()*+,-./:;=?@[]_`{|}~'.'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
   //private $soup = '!#%()*+,-./:;=?@[]_`{|}~'.'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';   //不能有“&<>”这三个符号，否则VM生成积木会出现不报错的异常：不显示积木块。

   private $soup = '#%,.:;?@[]_`{|}~'.'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';   //不能有“&<>”这三个符号，否则VM生成积木会出现不报错的异常：不显示积木块。

   //Scratch3.0的20字符ID生成器
   private function uid()
   {
      //global $soup;
      $id = Array();
      for ($i = 0; $i < 20; $i++) {
         $id[$i] = $this->soup[mt_rand(0,77) ];		//87个字符中随机抽一个
      }
      return "ID_".implode('',$id)."_DI";
   }

   //类初始化
   public function __construct() 
   {

   }

   /*************************************************
   *
   *  预处理逻辑表达式
   *
   *  按照括号进行拆分，未拆分部分进行正常的RPN处理
   *  被拆下的数据，进入下一轮预处理；被拆数据原本位置用不会被正则匹配影响的UID替换
   *
   *
   *************************************************/
   function preTreat($strExpression)
   {
      $nBraceBeginPos=strpos($strExpression,'(');		//寻找括号开始的位置
      if($nBraceBeginPos===false) return Array($strExpression,NULL);//表达式内无括号，返回空数组和原始字符串

      $arrExpressions=Array();					//保存被拆分下来的字符串  {UID=>被拆分数据}
      $strExpressionWithUID='';					//被拆剩的字符串，里面含有UID

      $str=substr($strExpression,0,$nBraceBeginPos);		//第一个括号前的数据，不需要trim
      if($str!='')
      {
         $strExpressionWithUID=$str;				//保存括号前的数据
      }      

      $strLen=strlen($strExpression);				//遍历字符串，寻找括号结束的地方
      $nBraceCounter=1;						//小括号计数器
      $nBraceEndPos=-1;						//小括号结束位置

      $n=0;

      while($nBraceBeginPos!==false && $nBraceBeginPos<$strLen)	//当找不到括号起点，或者起点超过字符串长度时，终止遍历。
      {
         for($n=$nBraceBeginPos+1;$n<$strLen;$n++)		//从小括号开始的位置的下一个字符开始检测
         {
            if($strExpression[$n]=='(') $nBraceCounter++;
            if($strExpression[$n]==')') $nBraceCounter--;
            if($nBraceCounter==0)				//小括号计数器归零，表示当前这段括号完整地结束了。
            {  
               $nBraceEndPos=$n;
               break;
            }
         }

         if($nBraceCounter!=0)  return Array(NULL,NULL);	//for循环结束，如果小括号计数器不为0，则说明小括号不匹配，表达式异常

         if($nBraceEndPos<=$strLen)				//小括号结束位置不能超过字符串长度。
         {
            $strSUBID=$this->uid();				//有小括号的数据需要提取出来，用UID来代替。
            $strBraced=trim(substr($strExpression,$nBraceBeginPos+1,$nBraceEndPos-$nBraceBeginPos-1));	//顺便去掉最外层的括号。
            $strExpressionWithUID.=" ".$strSUBID." ";
            $nBraceBeginPos=strpos($strExpression,'(',$nBraceEndPos+1);	//搜索下一个小括号开始的位置
            $arrResult=$this->preTreat($strBraced,$strSUBID);    //对拆分下来的数据进行再次检测

            if($nBraceBeginPos!==false)
            {
               $strExpressionWithUID.=substr($strExpression,$nBraceEndPos+1,$nBraceBeginPos-$nBraceEndPos-1);//获取当前小括号到下一个小括号之间的字符串。
               if($arrResult[1]!=NULL)    				//还能按照括号进行拆分
               {
                  $arrExpressions[$strSUBID]=$arrResult;
               }
               else							//不能继续拆分了，直接返回。
               {
                  $arrExpressions[$strSUBID]=Array($strBraced,NULL);	//无拆分，直接用字符串
               } 
            }
            else//搜不到了，可能全部结束了，或者后面有不含括号的数据。
            {
               $strExpressionWithUID.=substr($strExpression,$nBraceEndPos+1,$strLen-$nBraceEndPos-1);//获取当前小括号到下一个小括号之间的字符串。

               if($strSUBID==trim($strExpressionWithUID))
               {
                  return $arrResult;
               }
               else
               {
                  if($arrResult[1]!=NULL)    				//还能按照括号进行拆分
                  {
                     $arrExpressions[$strSUBID]=$arrResult;
                  }
                  else							//不能继续拆分了，直接返回。
                  {
                     $arrExpressions[$strSUBID]=Array($strBraced,NULL);	//无拆分，直接用字符串
                  } 
               }
               break;
            }

            $nBraceEndPos=-1;
            $nBraceCounter=1;
         }
         else break;
      }

      $strExpressionWithUID=trim($strExpressionWithUID);
      return Array($strExpressionWithUID,$arrExpressions);		//返回带UID的字符串和拆分后的数组
   }

   /*******************************************
   *
   *
   *   $arrData		传入的数据  Array( 0=> strExpression, 1=>Array())
   *
   *   $key		最后一条运算符操作需要修改的UID值
   *
   *   最终数据的第一组的最后一个的UID是最底层的UID
   *******************************************/
   public function build($arrData,$strKey)
   {
      //echo "\r\n\r\n".$strKey." strKey\r\n";
      $UIDX=$strKey=="0"?$this->uid():$strKey;		//首次进入，$strKey为0.  PHP8  0!=="0"

      //echo $UIDX."\r\n";
      $this->strExpression=$arrData[0];			//读取起始的字符串数据
      if($this->strExpression==NULL)  return FALSE;	//括号不匹配表达式异常

      //数组传入后，首要的任务就是解析第一条字符串数据
      $this->_expression = preg_split("/(\&\&)|(\|\|)|(>)|(<)|(==)|(!=)|(!)/",$this->strExpression,-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);//将字符串表达式转成数组
      $this->exp2RPN();					//转换为逆波兰表达式
      $arrChildUID=$this->toScratchJSON();

      $cuidCounter=count($arrChildUID);
      if($cuidCounter>0)
      {
         $arrChildUID[$cuidCounter-1][1]=$UIDX;		//修改key值，这个很重要，不能少，用于UID的传递。
      }

      if(!empty($arrChildUID[0]))
         $this->arrMainProcedure[]=$arrChildUID;//$this->toScratchJSON();	//拆分后的逻辑表达式
      else
      {
         $this->arrMainProcedure[]=Array(Array($UIDX,$this->strExpression));		//纯算术表达式
      }

      //字符串数据解析完毕，开始检测是否有下一级的数据
      $arrChildUID=$arrData[1];
      if($arrChildUID!=NULL)
      {
         foreach($arrChildUID as $key=>$arr)
         {
             $this->build($arr,$key);
         }
      }
   }

   //初始化数据
   //RPN初始化后，可以多次调用，所以表达式的初始化，没有放在类初始化里。
   public function init($strExpression)
   {
      $this->_rpnexp = Array();					//初始化。此类允许通过eval多次计算不同的表达式，所以初始化就放在这里了。
      $this->_stack = Array('#');
      $this->arrMainProcedure=Array();
      //$this->strExpression = $strExpression;	//先检查一下是否有缺失的乘

      //针对括号进行预处理
      $arrData=$this->preTreat($strExpression);

      //echo "预处理后数据：\r\n";
      //print_r($arrData);
      $this->build($arrData,"0");


      //echo "最终结果：\r\n\r\n";
      //print_r($this->arrMainProcedure);
      return $this->arrMainProcedure;

      //return $arrMainProcedure;

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
   *   当逻辑表达式被表示成“(A==B)”时，
   *   在对“==”进行拆分后，变量就会变成“(A”和“B)”，
   *   此时，需要清理。
   *
   ********************************************************/
   function checkBrace($strData)
   {
      if(strpos($strData,'(')===false && strpos($strData,')')===false) return $strData;	//字符串中没有括号，不需要处理。
      $nLeft=$nRight=0;
      for($i=0;$i<strlen($strData);$i++)
      {
         if($strData[$i]=='(') $nLeft++;
          if($strData[$i]==')') $nRight++;
      }
      $nTrimBrace=$nLeft-$nRight;
      if($nTrimBrace>0)					//左括号多了
         return substr($strData,$nTrimBrace);
      else if($nTrimBrace<0)				//右括号多了
         return substr($strData,0,strlen($strData)+$nTrimBrace);
      return $strData;					//括号匹配，原字符串没有问题。
   }

   //0.初始化逆波兰表达式数组
   //1.将四则混合运算字符串拆分为数组（若按原算法，拆分后的操作数只能是个位数。）
   //2.将数组按逆波兰排列
   //3.计算结果
   public function eval()
   {
      return $this->evalRPN( );			//计算逆波兰表达式，并返回计算结果
   }

   //对已拆分数组进行逆波兰处理
   private function exp2RPN() 
   {
      $this->_rpnexp=NULL;
      $nCheckParenthesis=0;					//用于检测左右括号是否对应
      $len = count($this->_expression);				//字符串表达式已经转成了数组
      for($i = 0; $i < $len; $i++)				//遍历数组
      {
         if(DEBUG_L)
         {
            echo "\nLOOP: ".$i. "\t ORDER:". count($this->_rpnexp)."\n";
            echo "\n\n++++++++++++STACK+++++++++++:\t";print_r($this->_stack);
            echo "\n\nRPN:\t";print_r($this->_rpnexp);
         }

         $str = trim($this->_expression[$i]);				//清理空格
         //echo "$i : [".$str."]\r\n";


         if ($str == '(')						//括号优先级最高，先检测是否有左括号出现
         {
            $nCheckParenthesis++;						//遇到左括号，加1；遇到右括号，减1
            $this->_stack[] = $str;						//将左括号压入运算符号堆栈
            //echo "将括号）压入堆栈\r\n";

            continue;								//立刻进入下一次循环
         } 
         else if ( !in_array($str, $this->_operator)) 			//非已定义的运算符号，即为操作数/变量
         {
            //echo "其它数据: $str\r\n";
            if($str!='')						//屏蔽掉空数据
               $this->_rpnexp[] = $this->checkBrace($str);		//清除多余的括号。	//放入输出结果数组中
            continue;								//立刻进入下一次循环
         }
         else if ($str == ')')						//右括号出现，表示有一个完整的括号结束了
         {
            $nCheckParenthesis--;						//遇到左括号，加1；遇到右括号，减1
            //echo "括号结束\n";
            //print_r($this->_stack);
            //echo "堆栈情况↑\r\n";

            for($j = count($this->_stack); $j >= 0; $j--)			//倒序检测运算符堆栈，把这一对括号中的操作都输出
            {
               $tmp = array_pop($this->_stack);						//取出堆栈顶的数据
               //echo "[".$tmp."]\r\n";
               if ($tmp == '(') { break;}							//直到处理完当前的整个括号内数据
               else $this->_rpnexp[] = $tmp;						//需要将该数据放入输出结果数组中
               //echo "tmp:".$tmp."\r\n";

            }

            //print_r($this->_stack);
            //echo "处理结束\r\n";

            continue;								//立刻进入下一次循环
         }
         else if (isset($this->_priority[end($this->_stack)]) && $this->_priority[$str] <= $this->_priority[end($this->_stack)]) //非括号内，非操作数，即为“+、-、*、/”四个操作，需要判断优先级
         {								  	//当前操作优先级比堆栈中最后一个的操作低，则需要处理减法问题
            //echo DEBUG_L?"优先级变化:当前操作：$str\t上一个操作：".end($this->_stack)."\n":'';

            //echo "优先级改变时，数据处理情况↓\r\n";
            //var_dump($this->_rpnexp);

            $this->_rpnexp[] = array_pop($this->_stack);			//这个操作，无论“+、-、*、/”，都要追加到结果数组中

            if(count($this->_stack)>1)        // && ($str=='||'))		//($str=='-'|| $str=='+') 即  $this->_priority[$str]==2
            {									//当$_stack中已经寄存有未处理的操作符，而当前又出现权限较低的+和-时
               $val1=array_pop($this->_stack);					//获取$_stack堆栈顶部数据
               if($val1=='&&')							//当堆栈中积存的是“-”，则需要先算这个减法
               {
                  $this->_rpnexp[]=$val1;
               }
               else
                 array_push($this->_stack,$val1);				//否则把该操作符放回去
            }

            $this->_stack[] = $str;						//将当前运算符压入堆栈

           //echo "优先级改变时，数据处理情况↓\r\n";

            continue;								//立刻进入下一次循环
         } 
         else								//当前操作优先级高
         {
            //echo "else :[".$str."]\r\n";
            if($str!='')
               $this->_stack[] = $str;						//直接将操作符压入堆栈
            continue;								//直接进入下一次循环
         }
      }

      for($i = count($this->_stack); $i >= 0; $i--)			//检测堆栈中是否有遗漏的操作
      {
         if (end($this->_stack) == '#') break;					//检测到达底部，结束
         $this->_rpnexp[] = array_pop($this->_stack);				//直接追加到结果数组
      }

      //var_dump($this->_rpnexp);
      //echo "result\r\n";
      if( $nCheckParenthesis!=0 ) $this->_rpnexp=  FALSE;
      return $this->_rpnexp;						//如果输入数据有误（比如括号不匹配，连续多个运算符叠加的情况暂时没有处理），就返回FALSE；否则返回包含逆波兰表达式数据的数组
   }

   public function toScratchJSON()
   {
      //echo "RAW\r\n";
      //print_r($this->_rpnexp);

      $jsonArr=Array();
      $data=Array();
      $type	= Array('>','<','==','&&','||',"!=","!");				//限定了只能处理这四种运算
      if($this->_rpnexp===FALSE) return '';

      for($i=0;$i<count($this->_rpnexp);$i++)
      {
         //echo "data:\r\n";
         //print_r($data);
         if(  $this->_rpnexp[$i]!=NULL)//数据莫名其妙地多了一个NULL，暂时屏蔽。
         {
            if(!in_array($this->_rpnexp[$i],$type))		//非计算符号，则认定为数字/变量
            {
               if(!is_numeric($this->_rpnexp[$i])) $bFormula=TRUE;
               array_unshift($data,$this->_rpnexp[$i]);	//将数据(数字/变量)插入到数组$data的开头
               //array_unshift($data,$this->_rpnexp[$i]);	//将数据(数字/变量)插入到数组$data的开头
            }
            else							//处理“+,-,*,/”
            {
               $val1=array_shift($data);				//获取数组$data的第一个数据，并删除
               $val2=array_shift($data);				//获取数组$data的第一个数据，并删除
               $uid=$this->uid();
               if($this->_rpnexp[$i]=='!')				//单目操作
               {
                  array_unshift($data,$val2);				//推入已经弹出的数据
                  $jsonArr[]=Array($this->_rpnexp[$i], $uid, $val1);  	//opcode,uid,arg1
               }
               else							//双目操作
                  $jsonArr[]=Array($this->_rpnexp[$i], $uid, $val2, $val1);  //opcode,uid,arg1,arg2

               switch($this->_rpnexp[$i])
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
//echo "LOGIC\r\n";
//print_r($jsonArr);
      if(DEBUG_L)
      {
         print_r($data);
         print_r($jsonArr);
      }
      return $jsonArr;
   }


   //获取RPN数据，以数组的形式呈现
   public function getArrRPN()
   {
      return $this->_rpnexp;
   }

   //获取RPN数据，以字符串的形式呈现
   public function getStrRPN()
   {
      return ($this->_rpnexp===FALSE?NULL:implode(" ",$this->_rpnexp));
   }

   //获取表达式的计算结果
   public function getValueRPN()
   {
      return $this->nValue;
   }
};
//RPN类定义结束
?>