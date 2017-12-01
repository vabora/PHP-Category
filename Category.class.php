<?php
/**
 * Category 无级分类
 * @author vabora(王小平)
 * @create time 2017/12/1 19:27:22
 * @version 0.1.1 beta
 */
namespace Category;
class Category
{
    private $data = array();//分类数据集合array();
    private $list = array();//序列化的列表
    public $database = array();//分类数据库（虚拟数据）;
    public $fields = array(
        'id'=>'id',
        'parent'=>'parent',
        'path'=>'path',
    );//自定义字段名称

    public function __construct(array $data){
        $this->data = $data;
        $this->database = $data;
    }
    /**
     * 获取所有id字段
     * @return array
     */
    public function ids(){
        return array_column($this->data,$this->fields['id']);
    }
    /**
     * 获取对应的字段列
     * @param string $field 字段名称
     * default return 所有字段
     * @return array
     */
    public function column(string $field=null){
        return array_column($this->data,$field);
    }
    /**
     * 获取对应数据行
     * @param array $where 查询条件
     * default return 所有数据
     * @return array
     */
    public function row(array $where=array()){
        if(count($where)==0){return $this->data;}
        $rows = array();
        foreach($this->data as $item){
            if(count(array_intersect_assoc($item,$where))==count($where)){
                $rows[] = $item;
            }
        }
        return $rows;
    }
    /**
     * 获取指定id值的数据行
     * @param int id 分类ID
     * @return array
     */
    public function rowid(int $id){
        $rows = $this->row(array($this->fields['id']=>$id));
        if(count($rows)>0){return $rows[0];}
        else{return array();}
    }
    /**
     * 获取父级分类数据
     * @param int $id 当前分类id
     * @return array
     */
    public function getParent(int $id){
        $key = array_search($id,$this->ids());
        if($key!==false){
            $parentid = $this->data[$key][$this->fields['parent']];
            $pkey = array_search($parentid,$this->ids());
            return $this->data[$pkey];
        }
        return array();
    }
    /**
     * 获取子级分类(无递归)
     * @param string $parentid
     * @return array
     */
    public function getChild(int $parentid){
        return $this->row(array($this->fields['parent']=>$parentid));
    }
    /**
     * 获取所有下级分类(递归)
     * @param string $parentid
     * @return array
     */
    public function getChilds(string $parentid){
        if($parentid=='0'){return $this->data;}
        $path = $this->column($this->fields['path']);
        $parent = $this->rowid($parentid);
        $childs = array();
        foreach($path as $key=>$value){
            if(strpos($value,$parentid)!==false){
                if($value==$parent[$this->fields['path']]){continue;}
                $childs[] = $this->data[$key];
            }
        }
        return $childs;
    }
    /**
     * 获取新增id
     * @return int
     */
    public function newid(){
        if(count($this->data)>0){
            $ids = $this->ids();
            rsort($ids);
            return $ids[0]+1;
        }
        else{return 1;}
    }
    /**
     * tree()获取分类树
     * @return array
     */
    public function tree(){
        $tmp = $this->data;
        $ids = $this->ids();
        $pids = array_column($tmp,$this->fields['parent'],$this->fields['id']);
        arsort($pids);
        foreach($pids as $id=>$pid){
            if($pid!=0){
                $tmp[array_search($pid,$ids)]['children'][]=$tmp[array_search($id,$ids)];
                unset($tmp[array_search($id,$ids)]);
            }
        }
        return array_values($tmp);
    }
    /**
     * sequence()序列化分类数据
     * @param array $tree
     * @return array
     */
    public function sequence(array $tree){
        $iterator = new \RecursiveArrayIterator($tree);
        iterator_apply($iterator, 'self::traverseStructure', array($iterator));
        $tmp = $this->list;
        $arr = array();
        $num = -1;
        foreach($tmp as $item){
            foreach($item as $key=>$value){
                if($key==$this->fields['id']){$num+=1;}
                $arr[$num][$key]=$value;
            }
        }
        return $arr;
    }
    /**
     * 迭代器
     */
    private function traverseStructure($iterator) {
        if ( $iterator->hasChildren() ) {
            $children = $iterator->getChildren();
            iterator_apply($children, 'self::traverseStructure', [$children]);
        } else {
            array_push($this->list,array($iterator->key()=>$iterator->current()));
        }
        return true;
    }
    /**
     * 添加新的分类 add()
     * @param int $parentid 父级id *值为0是创建顶级目录
     * @param array $extends 分类扩展属性（如：array('name'=>'分类一')）
     * @return array 新增分类信息
     */
    public function add(array $extends=array(),int $parentid=0){
        $parent = $this->row(array($this->fields['id']=>$parentid));
        $id = $this->newid();
        $path = count($parent)>0?$parent[$this->fields['path']].','.$id:$id;
        $new = array($this->fields['id']=>$id,$this->fields['parent']=>$parentid,$this->fields['path']=>$path);
        $new = array_merge($new,array_merge($extends,$new));
        array_push($this->database,$new);
        return $new;
    }

    /**
     * 更新分类 update()
     * @param int $id 需要更新的id
     * @param array $data 更新的数据
     * @return array 更新信息数据
     */
    public function update(int $id,array $data){
        $old=$this->rowid($id);
        if(count($old)==0){return array('status'=>false,'message'=>'不存在当前分类','data'=>array());}
        $pathkey = $this->fields['path'];
        $oldpath = $old[$pathkey];
        $tmp = array($this->fields['id']=>$id,$pathkey=>$oldpath);
        $data = array_merge($data,$tmp);
        $pkey = $this->fields['parent'];
        $newdata = array();
        $newdata[] = array_merge($old,$data);
        if(array_key_exists($pkey,$data)){
            if($old[$pkey]!=$data[$pkey]){
                $child = $this->getChilds($id);
                if(in_array($data[$pkey],array_column($child,$this->fields['id']))){
                    return array('status'=>false,'message'=>'不能将父级分类移动到子类','data'=>array());
                }
                $parent = $this->rowid($data[$pkey]);
                if(count($parent)>0){
                    $newpath = $parent[$pathkey].','.$id;
                    $newdata[0] = array_merge($newdata[0],array($pathkey=>$newpath));
                    $childs = $this->_updatechild($id,$newpath);
                    if(count($childs)>0){
                        array_unshift($childs,$newdata[0]);
                        $newdata = $childs;
                    }
                }
            }
        }
        $this->_update($newdata);
        return array('status'=>true,'message'=>'更新成功','data'=>$newdata);
    }
    /**
     * 删除分类 delete()
     * @param int $id 分类id
     * @param bool $isdeep 深度删除，包含所有子类
     * @return array 删除操作状态
     */
    public function delete(int $id,bool $isdeep=false){
        $cate = $this->rowid($id);
        $result = array('status'=>false,'message'=>'','data'=>array());
        if(count($cate)>0){
            $childs = $this->getChilds($id);
            if(count($childs)>0){
                if(!$isdeep){$result['message']='含有子类，删除失败';}
                else{
                    $result['status']=true;
                    $result['message']='预删除成功(含子类)';
                    $result['data'] = array_column($childs,$this->fields['id']);
                    array_unshift($result['data'],$id);
                }
            }
            else{
                $result = array('status'=>true,'message'=>'预删除成功','data'=>array($id));
            }
        }
        $this->_delete($result['data']);
        return $result;
    }
    /**
     * 更新分类 _updatechild()
     * @param int $parentid 父级id
     * @param string $path 父级path
     * @return array 更新子级数据
     */
    private function _updatechild(int $parentid,string $path){
        $pkey = $this->fields['parent'];
        $pathkey = $this->fields['path'];
        $childs = $this->getChilds($parentid);
        $data = array();
        foreach($childs as $child){
            $newpath = $path.','.$child[$pathkey];
            $newpath =  implode(',',array_unique(explode(',',$newpath)));
            $data[] = array_merge($child,array($pathkey=>$newpath));
        }
        return $data;
    }
    /**
     * 更新数据 _update()
     * @param array $data 更新的数据集合
     */
    private function _update(array $data){
        $old = $this->database;
        $idkey = $this->fields['id'];
        $ids = array_column($data,$idkey);
        $oldids = array_column($old,$idkey);
        foreach($ids as $id){
            $this->database[array_search($id,$oldids)]=$data[array_search($id,$ids)];
        }
    }
    /**
     * 删除数据 _delete()
     * @param array $id 删除的id或id集合
     */
    private function _delete(array $id){
        $old = $this->database;
        $ids = array_column($old,$this->fields['id']);
        foreach($id as $key){
            unset($this->database[array_search($key,$ids)]);
        }
        $this->database = array_values($this->database);
    }
}
