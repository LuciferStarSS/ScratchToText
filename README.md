# ScratchToText
Convert the script of the Scratch Project to a C-LIKE programming language.

该程序实现了将Scratch3.0项目中的脚本转换成文本代码的功能。

新增：

1.  rpn_calc_expression.class.php
拆分解析多则混合运算表达式

2.  rpn_logic_expression.class.php
拆分解析逻辑运算表达式

当前版本，支持自制积木（自定义函数）的解析和生成。
<img src=demo3.png>
![演示效果](https://github.com/LuciferStarSS/ScratchToText/blob/main/test.png?raw=true)

convertToC.php
实现将Scratch3.0的积木的JSON数据转换成类C语言文本代码

convertToB.php
实现将类C语言文本代码转换成Scratch3.0的积木的JSON数据

最新更新（2025-09-20）
1.修正自制积木的参数部分的bug，兼容Scratch2.0的参数设定；
2.修正分支结构内积木的参数设定；
3.修正部分逻辑表达内数据的传递。
4.文本转积木操作，不再破坏适用于所有角色的变量的ID，也就不会影响其他角色对该类变量的调用。

此程序为 https://github.com/LuciferStarSS/Scratch3.0_for_class 的一个扩展功能。
