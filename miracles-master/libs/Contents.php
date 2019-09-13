<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/* 
 * 该文件大部分代码来自 熊猫小A(AlanDecode) 的项目，感谢~
 * https://github.com/AlanDecode/typecho-theme-dev-framework
 */
 
class Contents
{
    /**
     * 内容解析器入口
     * 传入的是经过 Markdown 解析后的文本
     */
    static public function parseContent($data, $widget, $last)
    {
        $text = empty($last) ? $data : $last;
        if ($widget instanceof Widget_Archive) {
            //Prism 高亮
            $text = preg_replace('/<pre><code>/s','<pre><code class="language-html">',$text);
		    //FancyBox
	        $text = preg_replace('/<img(.*?)src="(.*?)"(.*?)alt="(.*?)"(.*?)>/s','<center><a data-fancybox="gallery" href="${2}" class="gallery-link"><img${1}src="${2}"${3}></a></center>',$text); 
	        //LazyLoad
		    $text = preg_replace('/<img (.*?)src(.*?)(\/)?>/','<img $1src="/usr/themes/Miracles/images/loading.gif" data-original$2 />',$text);
		
		    //气泡
		    $text = preg_replace('/\[bubble\](.*?)\[\/bubble\]/s','<div class="bubble post-bubble"><div class="saying-content"><p>${1}</p></div></div>',$text);
		
		    //Tip without var
		    $text = preg_replace('/\[tip\](.*?)\[\/tip\]/s','<div class="tip"><div class="container-fluid"><div class="row"><div class="col-1 tip-icon"><i class="iconfont icon-info"></i></div><div class="col-11 tip-content">${1}</div></div></div></div>',$text);
		    //Tip
		    $text = preg_replace('/\[tip type="(.*?)"\](.*?)\[\/tip\]/s','<div class="tip ${1}"><div class="container-fluid"><div class="row"><div class="col-1 tip-icon"><i class="iconfont icon-info"></i></div><div class="col-11 tip-content">${2}</div></div></div></div>',$text);

		    //解析友链盒子
	        $reg = '/\[links\](.*?)\[\/links\]/s';
            $rp = '<div class="links-box container-fluid"><div class="row">${1}</div></div>';
            $text = preg_replace($reg,$rp,$text);
		    //解析友链项目
	        $reg = '/\[(.*?)\]\{(.*?)\}\((.*?)\)/s';
            $rp = '<div class="col-lg-2 col-6 col-md-3 links-container">
		    <a href="${2}" target="_blank" class="links-link">
			  <div class="links-item">
			    <div class="links-img" style="background:url(\'${3}\');width: 100%;padding-top: 100%;background-repeat: no-repeat;background-size: cover;"></div>
				<div class="links-title">
				  <h4>${1}</h4>
				</div>
		      </div>
			  </a>
			</div>';
            $text = preg_replace($reg,$rp,$text);
        }
        return $text;
    }
    
	/**
     * 通过查询数据库
     * 获取上级评论人
     */
    public static function getParent($comment)
    {
        $recipients = [];
        $db = Typecho_Db::get();
        $widget = new Widget_Abstract_Comments(new Typecho_Request(), new Typecho_Response());
        // 查询
        $select = $widget->select()->where('coid' . ' = ?', $comment->parent)->limit(1);
        $parent = $db->fetchRow($select, [$widget, 'push']); // 获取上级评论对象
        if ($parent && $parent['mail']) {
            $recipients = [
                'name' => $parent['author'],
                'mail' => $parent['mail'],
            ];
        }
        return $recipients;
    }
	
	//从这里开始的代码来自 熊猫小A (https://imalan.cn)
    /**
     * 根据 id 返回对应的对象
     * 此方法在 Typecho 1.2 以上可以直接调用 Helper::widgetById();
     * 但是 1.1 版本尚有 bug，因此单独提出放在这里
     * 
     * @param string $table 表名, 支持 contents, comments, metas, users
     * @return Widget_Abstract
     */
    public static function widgetById($table, $pkId)
    {
        $table = ucfirst($table);
        if (!in_array($table, array('Contents', 'Comments', 'Metas', 'Users'))) {
            return NULL;
        }
        $keys = array(
            'Contents'  =>  'cid',
            'Comments'  =>  'coid',
            'Metas'     =>  'mid',
            'Users'     =>  'uid'
        );
        $className = "Widget_Abstract_{$table}";
        $key = $keys[$table];
        $db = Typecho_Db::get();
        $widget = new $className(Typecho_Request::getInstance(), Typecho_Widget_Helper_Empty::getInstance());
        
        $db->fetchRow(
            $widget->select()->where("{$key} = ?", $pkId)->limit(1),
                array($widget, 'push'));
        return $widget;
    }

    /**
     * 输出完备的标题
     */
    public static function title(Widget_Archive $archive)
    {
        $archive->archiveTitle(array(
            'category'  =>  '分类 %s 下的文章',
            'search'    =>  '包含关键字 %s 的文章',
            'tag'       =>  '标签 %s 下的文章',
            'author'    =>  '%s 发布的文章'
        ), '', ' - ');
        Helper::options()->title();
    }

    /**
     * 返回上一篇文章
     */
    public static function getPrev($archive)
    {
        $db = Typecho_Db::get();
        $content = $db->fetchRow($db->select()->from('table.contents')->where('table.contents.created < ?', $archive->created)
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', $archive->type)
            ->where('table.contents.password IS NULL')
            ->order('table.contents.created', Typecho_Db::SORT_DESC)
            ->limit(1));
        
        if($content) {
            return self::widgetById('Contents', $content['cid']);    
        }else{
            return NULL;
        }
    }

    /**
     * 返回下一篇文章
     */
    public static function getNext($archive)
    {
        $db = Typecho_Db::get();
        $content = $db->fetchRow($db->select()->from('table.contents')->where('table.contents.created > ? AND table.contents.created < ?',
            $archive->created, Helper::options()->gmtTime)
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.type = ?', $archive->type)
                ->where('table.contents.password IS NULL')
                ->order('table.contents.created', Typecho_Db::SORT_ASC)
                ->limit(1));

        if($content) {
            return self::widgetById('Contents', $content['cid']);    
        }else{
            return NULL;
        }
    }

    /**
     * 最近评论，过滤引用通告，过滤博主评论
     */
    public static function getRecentComments($num = 10)
    {
        $comments = array();

        $db = Typecho_Db::get();
        $rows = $db->fetchAll($db->select()->from('table.comments')->where('table.comments.status = ?', 'approved')
            ->where('type = ?', 'comment')
            ->where('ownerId <> authorId')
            ->order('table.comments.created', Typecho_Db::SORT_DESC)
            ->limit($num));

        foreach ($rows as $row) {
            $comment =  self::widgetById('Comments', $row['coid']);
            $comments[] = $comment;
        }

        return $comments;
    }
}