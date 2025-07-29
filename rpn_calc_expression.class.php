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
define( 'DEBUG_C', FALSE);						//调试用

class RPN_CALCULATION_EXPRESSION {
   public  $strExpression = '';						//待计算的字符串表达式
   public  $strRPN	  = '';						//生成的逆波兰表达式
   public  $nValue	  = 0;						//表达式计算的结果

   private $_arrExpression   = Array();					//拆分为数组的计算表达式
   private $_rpnexp 	  = Array();					//队列，用于存放逆波兰表达式
   private $_stack 	  = Array('#');					//堆栈，用于存放操作符
   private $_priority 	  = Array('#' => 0, '(' =>1, '*' => 3,    	//计算优先级设定
				  '/' => 3, '+' =>2, '-' => 2);
   private $_operator 	  = Array('(', '+', '-', '*', '/', ')');	//四则混合运算中的操作符

   private $soup2 = '!#%()*+,-./:;=?@[]_`{|}~'.'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
   private $soup = '!#%()*+,-./:;=?@[]_`{|}~'.'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';   //不能有“&<>”这三个符号，否则VM生成积木会出现不报错的异常：不显示积木块。


   //Scratch3.0的20字符ID生成器
   private function uid()
   {
      //global $soup;
      $id = Array();
      for ($i = 0; $i < 20; $i++) {
         $id[$i] = $this->soup[mt_rand(0,85) ];		//87个字符中随机抽一个
      }
      return "ID_".implode('',$id)."_DI";
   }

   //类初始化
   public function __construct() 
   {

   }

   /****************************************************
   预处理字符串

       1.把缺省的乘号添进去：
           例1：2(3+4)  ->  2*(3+4)
       2.负括号开头的表达式，在前面补0：
           例2：-(1+2） ->  0-(1+2)
           如果是-1+2，则在拆分后再处理
   ****************************************************/
   public function preTreat($strExpression)
   {
      $strResult="";
      $lastOpt='';
      if($strExpression[0]=='-' && isset($strExpression[1]) && $strExpression[1]=='(')			//解决一开始出现负括号的现象：-(1+2)
         $strExpression='0'.$strExpression;

      $strLen=strlen($strExpression);
      for($n=0;$n<$strLen;$n++)								//在()()间添加缺省的*
      {
         //if($strExpression[$n]!='(') $lastOpt=$strExpression[$n];

         if($this->checkOptChar($strExpression[$n])) $lastOpt=$strExpression[$n];	//记录下操作符：+-*/

         else if($n>0 &&  is_numeric($strExpression[$n-1]) && $strExpression[$n]=='(' ) 
         {
            if($lastOpt=="/")
               $strResult.="/";
            else
               $strResult.="*";
            $strLen++;
         }

/*       //数字与变量的组合就不处理了，太复杂，会涉及到多个变量在一起的情况，在数学上，一个变量一个字母还可以，在计算机原理，变量可以有多个，还可以有数字，不适合省掉操作符。
         if($n>0 && !$this->checkOptChar($strExpression[$n]) &&  !is_numeric($strExpression[$n]) &&  is_numeric($strExpression[$n-1])) 
         {
echo '['.($lastOpt).']';
            if($lastOpt=="/")
               $strResult.="/";
            else
               $strResult.="*";
         }
*/
         $strResult.=$strExpression[$n];
      }
      return $strResult;
   }


   //检测当前字符是否是四则运算操作符
   function checkOptChar($opt)
   {
      switch($opt)
      {
         case '+': 
         case '-': 
         case '*': 
         case '/': return 1;
         //case ')': 
         //case '(': return 0;
         default: return 0;
      }
   }

   //初始化数据
   //RPN初始化后，可以多次调用，所以表达式的初始化，没有放在类初始化里。
   public function init($strExpression)
   {
      if($strExpression=='') return NULL;	//为空，结束。这种情况已经在执行前排除，所以可以不判断。

      $this->_rpnexp = Array();				//初始化。此类允许通过eval多次计算不同的表达式，所以初始化就放在这里了。
      $this->_stack = Array('#');			//操作符堆栈
      $this->strExpression = $this->preTreat($strExpression);//$strExpression;	//预处理，解决两种情况。
      $this->_arrExpression = preg_split("/(\+)|(\-)|(\*)|(\/)|(\()|(\))/",$this->strExpression,-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);//将字符串表达式转成数组

      if(count($this->_arrExpression)==1) return $strExpression;//$this->exp2RPN();				//没有可拆分的运算表达式，可能是单个变量，直接返回。

      //拆分后处理
      if($this->_arrExpression[0]=='-')			//解决负数开始的表达式：-1+3    -1*3
      {
         if(is_numeric($this->_arrExpression[1]))	//之所以不把这个处理放在preTreat里，是因为-1是一个操作数，不能用补零来处理。
         {
            $this->_arrExpression[0]= $this->_arrExpression[0]. $this->_arrExpression[1];	//把第一和第二个数据拼合在一起
            for($i=1;$i<count($this->_arrExpression)-1;$i++)					//后面的数据往前挪
            {
               $this->_arrExpression[$i]=$this->_arrExpression[$i+1];
            }
            unset($this->_arrExpression[$i]);							//删除最后一个数据
         }
      }

      $i=0;						//解决括号中以负数开始的表达式：(-1+3)
      $n=count($this->_arrExpression)-1;		//这里也不能补零。
      $bShift=false;
      while($i<$n)
      {
          $i++;
          if($this->_arrExpression[$i]=='-' && $this->_arrExpression[$i-1]=='(' )
          {
              $this->_arrExpression[$i]=$this->_arrExpression[$i].$this->_arrExpression[$i+1];	//把当前数据与下一条数据拼合在一起
              for($j=$i+1;$j<count($this->_arrExpression)-1;$j++)				//后面的数据往前挪
              {
                 $this->_arrExpression[$j]=$this->_arrExpression[$j+1];
              }
              unset($this->_arrExpression[$j]);							//删除最后一条数据
              $i++;										//跳过一条数据
              $n--;										//数组长度加1
          }
      }

      $i=0;						//解决括号间省略乘法的表达式：(1+3)(1+2)
      $n=count($this->_arrExpression)-1;
      $bShift=false;
      while($i<$n)
      {
          $i++;
          if($this->_arrExpression[$i]=='(' && $this->_arrExpression[$i-1]==')' )	
          {
              for($j=$n;$j>=$i;$j--)								//倒着，将当前数据往后移
              {
                 $this->_arrExpression[$j+1]=$this->_arrExpression[$j];
              }
              $this->_arrExpression[$i]="*";							//插入一个乘号
              $i++;
              $n++;										//数组长度加1
          }
      }
      $this->exp2RPN();					//转换为逆波兰表达式
      return TRUE;			//默认返回TRUE，如果是字符串，表示无运算表达式
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
      $nCheckParenthesis=0;					//用于检测左右括号是否对应
      $len = count($this->_arrExpression);				//字符串表达式已经转成了数组
      for($i = 0; $i < $len; $i++)				//遍历数组
      {
         if(DEBUG_C)
         {
            echo "\nLOOP: ".$i. "\t ORDER:". count($this->_rpnexp)."\n";
            echo "\n\n++++++++++++STACK+++++++++++:\t";print_r($this->_stack);
            echo "\n\nRPN:\t";print_r($this->_rpnexp);
         }

         $str = trim($this->_arrExpression[$i]);				//清理空格
         if ($str == '(')						//括号优先级最高，先检测是否有左括号出现
         {
            $nCheckParenthesis++;						//遇到左括号，加1；遇到右括号，减1
            $this->_stack[] = $str;						//将左括号压入运算符号堆栈
            continue;								//立刻进入下一次循环
         } 
         else if ( !in_array($str, $this->_operator)) 			//非已定义的运算符号，即为操作数/变量
         {
            $this->_rpnexp[] = $str;						//放入输出结果数组中
            continue;								//立刻进入下一次循环
         }
         else if ($str == ')')						//右括号出现，表示有一个完整的括号结束了
         {
            $nCheckParenthesis--;						//遇到左括号，加1；遇到右括号，减1
            for($j = count($this->_stack); $j >= 0; $j--)			//倒序检测运算符堆栈，把这一对括号中的操作都输出
            {
               $tmp = array_pop($this->_stack);						//取出堆栈顶的数据
               if ($tmp == "(") break;							//直到处理完当前的整个括号内数据
               else $this->_rpnexp[] = $tmp;						//需要将该数据放入输出结果数组中
            }
            continue;								//立刻进入下一次循环
         }
         else if (isset($this->_priority[end($this->_stack)]) && $this->_priority[$str] <= $this->_priority[end($this->_stack)]) //非括号内，非操作数，即为“+、-、*、/”四个操作，需要判断优先级
         {								  	//当前操作优先级比堆栈中最后一个的操作低，则需要处理减法问题
            echo DEBUG_C?"优先级变化:当前操作：$str\t上一个操作：".end($this->_stack)."\n":'';

            $this->_rpnexp[] = array_pop($this->_stack);			//这个操作，无论“+、-、*、/”，都要追加到结果数组中

            if(count($this->_stack)>1 && ($str=='-'|| $str=='+'))		//($str=='-'|| $str=='+') 即  $this->_priority[$str]==2
            {									//当$_stack中已经寄存有未处理的操作符，而当前又出现权限较低的+和-时
               $val1=array_pop($this->_stack);					//获取$_stack堆栈顶部数据
               if($val1=='-')							//当堆栈中积存的是“-”，则需要先算这个减法
               {
                  $this->_rpnexp[]=$val1;
               }
               else
                 array_push($this->_stack,$val1);				//否则把该操作符放回去
            }

            $this->_stack[] = $str;						//将当前运算符压入堆栈
            continue;								//立刻进入下一次循环
         } 
         else								//当前操作优先级高
         {
            $this->_stack[] = $str;						//直接将操作符压入堆栈
            continue;								//直接进入下一次循环
         }
      }

      for($i = count($this->_stack); $i >= 0; $i--)			//检测堆栈中是否有遗漏的操作
      {
         if (end($this->_stack) == '#') break;					//检测到达底部，结束
         $this->_rpnexp[] = array_pop($this->_stack);				//直接追加到结果数组
      }

//echo "RPNEXP\r\n";
  //    print_r($this->_rpnexp);

      if( $nCheckParenthesis!=0 ) $this->_rpnexp=  FALSE;
      return $this->_rpnexp;						//如果输入数据有误（比如括号不匹配，连续多个运算符叠加的情况暂时没有处理），就返回FALSE；否则返回包含逆波兰表达式数据的数组
   }

   //获取表达式的计算结果
   public function evalRPN() 
   {
      if($this->_rpnexp===FALSE)
         return "括号匹配有问题。";

      $bFormula	= FALSE;
      $data	= Array();						//用于保存运算所需要的数/变量
      $type	= Array('+','-','*','/');				//限定了只能处理这四种运算

      for($i=0;$i<count($this->_rpnexp);$i++)
      {
         if(  $this->_rpnexp[$i]!=NULL)//数据莫名其妙地多了一个NULL，暂时屏蔽。
         {
            if(!in_array($this->_rpnexp[$i],$type))		//非计算符号，则认定为数字/变量
            {
               if(!is_numeric($this->_rpnexp[$i])) $bFormula=TRUE;
               array_unshift($data,$this->_rpnexp[$i]);	//将数据(数字/变量)插入到数组$data的开头
            }
            else							//处理“+,-,*,/”
            {
               $val1=array_shift($data);				//获取数组$data的第一个数据，并删除
               $val2=array_shift($data);				//获取数组$data的第一个数据，并删除
               switch($this->_rpnexp[$i])
               {
                  case '+':
                     array_unshift($data,$val2+$val1);		//将计算结果保存到数组$data的开头
                     break;
                  case '-':
                     array_unshift($data,$val2-$val1);
                     break;
                  case '*':
                     array_unshift($data,$val2*$val1);
                     break;
                  case '/':
                     array_unshift($data,$val2/$val1);
                     break;
                  //default:					//由于前面if里的in_array()已经过滤了非“+,-,*,/”的情况，
                  //   break;					//所以这里的default可以安心地去掉。
               }
            }
         }
      }
      return $this->nValue=$bFormula?FALSE:current($data);	//当输入里有无法计算的字母时，返回FALSE，否则返回计算后得到的数值。
   }

   public function toScratchJSON()
   {
      $jsonArr=Array();
      $data=Array();
      $type	= Array('+','-','*','/');				//限定了只能处理这四种运算
      if($this->_rpnexp===FALSE) return '';
      for($i=0;$i<count($this->_rpnexp);$i++)
      {

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
               $jsonArr[]=Array($this->_rpnexp[$i], $uid,$val2, $val1);  //opcode,uid,arg1,arg2

               switch($this->_rpnexp[$i])
               {
                  case '+':
                     array_unshift($data,$uid);		//将计算结果保存到数组$data的开头
                     break;
                  case '-':
                     array_unshift($data,$uid);
                     break;
                  case '*':
                     array_unshift($data,$uid);
                     break;
                  case '/':
                     array_unshift($data,$uid);
                     break;
                  //default:					//由于前面if里的in_array()已经过滤了非“+,-,*,/”的情况，
                  //   break;					//所以这里的default可以安心地去掉。
               }
            }
         }
      }
      if(DEBUG_C)
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