# ScratchToText
Convert the script of the Scratch Project to a C-LIKE programming language.

该程序实现了将Scratch3.0项目中的脚本转换成文本代码的功能。

新增：

1.  rpn_calc_expression.class.php
拆分解析多则混合运算表达式

2.  rpn_logic_expression.class.php
拆分解析逻辑运算表达式

最新更新的版本，支持自制积木（自定义函数）的解析和生成。

![演示效果](https://github.com/LuciferStarSS/ScratchToText/blob/main/test.png?raw=true)

使用方法：
   include "s2c.class.php";

   $d=file_get_contents("./sprite.json");	//.SB3文件中的project.json里的部分数据
   
   $scratch= new Scratch3ToC($d);		//初始化
   
   $scratch->compileSB3();			//转换编译
   
   $scratch->dumpCodeInC();			//输出结果
   
   file_put_contents("sc.txt",serialize($scratch->codeInC));	//数组结果写入文件

此程序为 https://github.com/LuciferStarSS/Scratch3.0_for_class 的一个扩展功能。
