# simple-php-template-engine
参照TP5的think引擎，写的简单的模板引擎
使用方法跟tp5方式类似

## 一、目前支持的方法：
### assign: 绑定数据

$this->assign('var', $var); 

//第一个参数是变量名，第二个参数是绑定数据

### fetch: 渲染模板

$this->fetch('/view.html');

//参数是模板路径

## 二、目前支持标签：
### volist: 循环输出标签
  {volist name='list' id='$v'}
  
  {/volist}
### if、else、elseif: 判断标签
{if condition='$var eq 1'}

{elseif condition='$var eq 2'}

{else/}

{/if}
