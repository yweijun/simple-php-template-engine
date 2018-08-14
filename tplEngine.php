<?php
/**
 * Created by PhpStorm.
 * User: yweijun
 * Date: 2018/8/9
 * Time: 16:30
 */

class tplEngine
{
    private $data = array();    // 绑定数据
    // 配置
    public $config = array(
        'tpl_start' => '{',     // 标签开始标志
        'tpl_end'   => '}',     // 标签结束标志
    );

    // 标签
    private $tags = array(
        'php'        => array('attr' => ''),
        'volist'     => array('attr' => 'name,id,offset,length,key,mod', 'alias' => 'iterate'),
        'foreach'    => array('attr' => 'name,id,item,key,offset,length,mod', 'expression' => true),
        'if'         => array('attr' => 'condition', 'expression' => true),
        'elseif'     => array('attr' => 'condition', 'close' => 0, 'expression' => true),
        'else'       => array('attr' => '', 'close' => 0),
        'switch'     => array('attr' => 'name', 'expression' => true),
        'case'       => array('attr' => 'value,break', 'expression' => true),
        'default'    => array('attr' => '', 'close' => 0),
        'compare'    => array('attr' => 'name,value,type', 'alias' => array('eq,equal,notequal,neq,gt,lt,egt,elt,heq,nheq', 'type')),
        'range'      => array('attr' => 'name,value,type', 'alias' => array('in,notin,between,notbetween', 'type')),
        'empty'      => array('attr' => 'name'),
        'notempty'   => array('attr' => 'name'),
        'present'    => array('attr' => 'name'),
        'notpresent' => array('attr' => 'name'),
        'defined'    => array('attr' => 'name'),
        'notdefined' => array('attr' => 'name'),
        'load'       => array('attr' => 'file,href,type,value,basepath', 'close' => 0, 'alias' => array('import,css,js', 'type')),
        'assign'     => array('attr' => 'name,value', 'close' => 0),
        'define'     => array('attr' => 'name,value', 'close' => 0),
        'for'        => array('attr' => 'start,end,name,comparison,step'),
        'url'        => array('attr' => 'link,vars,suffix,domain', 'close' => 0, 'expression' => true),
        'function'   => array('attr' => 'name,vars,use,call'),
    );

    // 比较表达式
    protected $comparison = array(' nheq ' => ' !== ', ' heq ' => ' === ', ' neq ' => ' != ', ' eq ' => ' == ', ' egt ' => ' >= ', ' gt ' => ' > ', ' elt ' => ' <= ', ' lt ' => ' < ');

    /**
     * 绑定数据
     * @param mixed $name 绑定变量名称
     * @param mixed $val 绑定变量数据
     */
    public function assign($name, $val = '') {
        if (is_array($name)) {
            // 如果是数组的话直接数据合并
            $this->data = array_merge($this->data, $name);
        } else {
            $this->data[$name] = $val;
        }
    }

    /**
     * 页面渲染
     * @param string $template 模板文件地址
     */
    public function fetch($template = '') {
        $content = $this->getContent($template);
        $this->parse($content);

        // 将绑定数据转为变量
        extract($this->data, EXTR_OVERWRITE);
//        dump($content);
        // 使用自定义流，将字符串输出
        include("var://" . $content);
    }

    /**
     * 解析内容
     * @param string $content 模板页面内容
     */
    private function parse(&$content) {
        // 内容为空，不解析
        if (empty($content)) {
            return;
        }
        $this->parseVarsTag($content);
        $this->parseTags($content);
    }

    /**
     * 解析模板标签
     * @param string $content 模板页面内容
     */
    private function parseTags(&$content) {
        $tags = array();    // 所有闭合和非闭合标签
        foreach ($this->tags as $name => $val) {
            // 判断是否闭合标签,默认闭合标签
            $close = !isset($val['close']) || $val['close'] ? 1 : 0;
            $tags[$close][$name] = $name;
            if (isset($val['alias'])) {
                // 别名设置
                $alias = (array) $val['alias'];
                foreach (explode(',', $alias[0]) as $v) {
                    $tags[$close][$v] = $name;
                }
            }
        }

        // 闭合标签
        if (!empty($tags[1])) {
            $nodes = array();   // 存放匹配到的标签节点
            $regex = $this->getRegex(array_keys($tags[1]), 1);

            if (preg_match_all($regex, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                $right = array();
                foreach ($matches as $match) {
                    if ($match[1][0] == '') {
                        // 为空说明是结束标签
                        $name = strtolower($match[2][0]);
                        // 判断开始标签数组里面有没有对应的标签
                        if (!empty($right[$name])) {
                            $nodes[$match[0][1]] = array(
                                'name' => $name,
                                'begin' => array_pop($right[$name]),
                                'end' => $match[0]
                            );
                        }
                    } else {
                        // 将开始标签存入数组
                        $right[strtolower($match[1][0])][] = $match[0];
                    }
                }
                unset($right, $matches);
                // 按标签在模板中的位置从后向前排序
                krsort($nodes);
            }
            // 中断字符
            $break = '###break###';
            // 有标签的话
            if ($nodes) {
                $beginArr = array();
                // 替换标签,因为是根据位置pos来替换标签内容，所以从底部标签往上替换
                foreach ($nodes as $pos => $node) {
                    $name = $node['name'];
                    $attrs = $this->parseAttr($node['begin'][0], $name);
                    $method = 'tag' . $name;
                    // 切分标签头尾替换的内容,$replace[0]是标签头,$replace[1]是标签尾
                    $replace = explode($break, $this->$method($attrs, $break));
                    if (count($replace) > 1) {
                        // 替换标签尾部
                        $content = substr_replace($content, $replace[1], $node['end'][1], strlen($node['end'][0]));
                        // 将标签头加入头数组
                        $beginArr[] = array(
                            'pos' => $node['begin'][1],
                            'len' => strlen($node['begin'][0]),
                            'str' => $replace[0]
                            );
                    }
                }
                // 替换标签头
                while($beginArr) {
                    $begin = array_pop($beginArr);
                    $content = substr_replace($content, $begin['str'], $begin['pos'], $begin['len']);
                }
            }
        }

        // 单标签
        if (!empty($tags[0])) {
            $regex = $this->getRegex($tags[0], 0);
            // 对单标签的每次匹配做替换
            $content = preg_replace_callback($regex, function ($matches) use (&$tags) {
                $name = $tags[0][strtolower($matches[1])];
                $attrs = $this->parseAttr($matches[0], $name);
                $method = 'tag'.$name;
                return $this->$method($attrs, '');
            }, $content);
        }
        return;
    }

    /**
     * 解析标签属性
     * @param string $str 需要解析的属性字符串
     * @param string $name 标签名
     * return array $result 属性数组
     */
    private function parseAttr($str, $name) {
        $regex  = '/\s+(?>(?P<name>[\w-]+)\s*)=(?>\s*)([\"\'])(?P<value>(?:(?!\\2).)*)\\2/is';
        $result = array();

        if (preg_match_all($regex, $str, $matches)) {
            // 属性和属性值对应
            foreach ($matches['name'] as $key => $val) {
                $result[$val] = $matches['value'][$key];
            }
        }

        return $result;
    }

    /**
     * 解析模板变量标签
     * @param string $content 模板页面内容
     */
    private function parseVarsTag(&$content) {
        $regex = $this->getRegex('var');
        if (preg_match_all($regex, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $str = stripslashes($match[1]);
                $flag = substr($str, 0, 1);
                switch ($flag) {
                    case '$':
                        $this->parseVar($str);
                        $str = '<?php echo '. $str . '; ?>';
                        break;
                    default:
                        return;
                }
                $content = str_replace($match[0], $str, $content);
            }
        }
    }

    /**
     * 解析变量
     * @param string $varStr 需要解析的变量字符
     */
    private function parseVar(&$varStr) {
        $varStr = trim($varStr);
//        $regex = '/\$[a-zA-Z_][0-9a-zA-Z_]*([\.][a-zA-Z_][0-9a-zA-Z_]*)*/';
        $regex = '/\$[a-zA-Z_](?>\w*)(?:[:\.][0-9a-zA-Z_](?>\w*))+/';
        // 对多维数组解析
        if (preg_match_all($regex, $varStr, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            while($matches) {
                $match = array_pop($matches);
                $vars = explode('.', $match[0][0]);
                $str = $vars[0];
                foreach ($vars as $v) {
                    if (strpos($v, '$') === false) {
                        $str .= '[\'' . $v . '\']';
                    }
                }
                $varStr = substr_replace($varStr, $str, $match[0][1], strlen($match[0][0]));
            }
        }
    }

    /**
     * 获取页面内容
     * @param string $template 模板页面的路径
     * return string 页面内容
     */
    private function getContent($template) {
        if (!is_file($template)) {
            throw new \Exception("file is not exists");
        }
        return file_get_contents($template);
    }

    /**
     * 解析volist标签
     * @param string $tagAttr 标签属性数组
     * @param string $break 标签之间隔断内容
     * return string 替换字符
     */
    private function tagVolist($tagAttr, $break) {
        // 获取各属性值
        $name = $tagAttr['name'];
        $id = $tagAttr['id'];
        $offset = !empty($tagAttr['offset']) && is_numeric($tagAttr['offset']) ? intval($tagAttr['offset']) : 0;
        $key = !empty($tagAttr['key']) ? $tagAttr['key'] : 'i';
        $length = !empty($tagAttr['length']) && is_numeric($tagAttr['length']) ? intval($tagAttr['length']) : 'null';
        $mod = isset($tagAttr['mod']) ? $tagAttr['mod'] : 2;
        $empty = isset($tagAttr['empty']) ? $tagAttr['empty'] : '';

        $parseStr = '<?php ';
        $name = $this->autoBuildVar($name);
        $parseStr .= 'if(is_array('. $name .')): $' . $key .'=0;';
        // 输出数组的长度
        if ($length !== 'null' || $offset !== 0) {
            $parseStr .= '$__LIST__ = is_array('. $name .')? array_slice('. $name .','. $offset .','. $length .',true) : array();';
        } else {
            $parseStr .= '$__LIST__ = '. $name .';';
        }
        $parseStr .= 'if(count($__LIST__) === 0): echo "'. $empty .'";';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key => '. $id .'):';
        // 余数控制
        $parseStr .= '$mod = ($'. $key .' % '. $mod .');';
        // 循环计数
        $parseStr .= '++$'. $key .';?>';
        $parseStr .= $break;
        $parseStr .= '<?php endforeach; endif; else: echo "'. $empty .'"; endif;?>';
        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * 解析If标签
     * @param string $tagAttr 标签属性数组
     * @param string $break 标签之间隔断内容
     * return string 替换字符
     */
    private function tagIf($tagAttr, $break) {
        $condition = !empty($tagAttr['expression']) ? $tagAttr['expression'] : $tagAttr['condition'];
        $condition = $this->parseCondition($condition);
        $parseStr = '<?php if ('. $condition .'): ?>'. $break .'<?php endif; ?>';
        return $parseStr;
    }

    /**
     * 解析elseif标签
     * @param string $tagAttr 标签属性数组
     * @param string $break 标签之间隔断内容
     * return string 替换字符
     */

    private function tagElseif($tagAttr, $break) {
        $condition = !empty($tagAttr['expression']) ? $tagAttr['expression'] : $tagAttr['condition'];
        $condition = $this->parseCondition($condition);
        $parseStr = '<?php elseif ('. $condition .'): ?>';
        return $parseStr;
    }

    /**
     * 解析else标签
     * @param string $tagAttr 标签属性数组
     * @param string $break 标签之间隔断内容
     * return string 替换字符
     */
    private function tagElse($tagAttr, $break) {
        $parseStr = '<?php else: ?>';
        return $parseStr;
    }

    /**
     * 解析表达式
     * @param string $condition 条件字符
     * return string 替换字符
     */
    private function parseCondition($condition) {
        $condition = str_ireplace(array_keys($this->comparison), array_values($this->comparison), $condition);
        $this->parseVar($condition);
        return $condition;
    }

    /**
     * 获取标签解析正则
     * @param string $tagName 标签名
     * @param int $close      是否闭合标签
     * return string 正则表达式
     */
    private function getRegex($tagName, $close = 0) {
        $regex = '';
        $begin = $this->config['tpl_start'];
        $end = $this->config['tpl_end'];
        switch ($tagName) {
            // 变量解析正则
            case 'var':
                $regex = $begin . '((?:[\$]{1,2}[a-wA-w_]|[\:\~][\$a-wA-w_]|[+]{2}[\$][a-wA-w_]|[-]{2}[\$][a-wA-w_]|\/[\*\/])(?>(?:(?!' . $end . ').)*))' . $end;
                break;
            default:
                $tagName = is_array($tagName) ? implode('|', $tagName) : $tagName;
                if ($close) {
                    $regex = $begin . '(?:(' . $tagName . ')\b(?>[^' . $end . ']*)|\/(' . $tagName . '))' . $end;
                } else {
                    $regex = $begin . '(' . $tagName . ')\b(?>[^' . $end . ']*)' . $end;
                }
                break;
        }
        return'/' . $regex . '/is';
    }

    /**
     * 自动识别构建变量
     * @param string $name 变量名
     * return string 变量
     */
    private function autoBuildVar(&$name) {
        $flag = substr($name, 0, 1);
        if ($flag != '$' && preg_match('/[a-zA-Z_]/', $flag)) {
            // 常量不需要解析
            if (defined($name)) {
                return $name;
            }
            $name = '$'.$name;
        }
        $this->parseVar($name);
        return $name;
    }
}

//自定义协议
class VariableStream {
    private $string;
    private $position;
    public function stream_open($path, $mode, $options, &$opened_path) {
        $path = str_replace('var://', '', $path);
        //根据ID到数据库中取出php字符串代码
        $this->string = $path;
        $this->position = 0;
        return true;
    }
    public function stream_read($count) {
        $ret =  substr($this->string, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }
    public function stream_eof() {}
    public function stream_stat() {}
}

stream_wrapper_register("var", "VariableStream");
