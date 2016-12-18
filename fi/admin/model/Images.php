<?php
namespace fi\admin\model;
/**
 * 图片空间业务处理
 */
use think\Db;
class Images extends Base{
	/**
	 * 获取图片空间概况
	 */
	public function summary(){
		$rs = Db::table('__IMAGES__')->where(['dataFlag'=>1])->field('fromTable,isUse,sum(imgSize) imgSize')->group('fromTable,isUse')
		        ->order('fromTable asc')->select();
		//获取目录名称
		$rs2 = Db::table('__DATAS__')->where(['catId'=>3])->field('dataName,dataVal')->select();
		$imagesMap = [];
		foreach ($rs2 as $key =>$v){
			$imagesMap[$v['dataVal']] = $v['dataName'];
		}
	    $images = [];
		foreach ($rs as $key =>$v){
			if(!isset($images[$v['fromTable']]))$images[$v['fromTable']] = ['directory'=>'','data'=>['0'=>0,'1'=>0]];
			if(isset($imagesMap[$v['fromTable']]))$images[$v['fromTable']]['directory'] = $imagesMap[$v['fromTable']];
		    $images[$v['fromTable']]['data'][$v['isUse']] = round($v['imgSize']/1024/1024,2);
		}
		$maxSize = 0;
		foreach ($images as $key =>$v){
			$size = (float)$v['data']['0']+(float)$v['data']['1'];
			if($maxSize<$size)$maxSize = $size;
		}
		$images['_FISummary_'] = $maxSize;
		return $images;
	}
	/**
	 * 获取记录
	 */
	public function pageQuery(){
		$key = input('keyword');
		$isUse = (int)input('isUse');
		$where = ['fromTable'=>$key,'a.dataFlag'=>1];
		if($isUse !=-1)$where['isUse'] = $isUse;
		$page = $this->alias('a')->join('__USERS__ u','a.ownId=u.userId and fromType=0','left')
		            ->join('__SHOPS__ s','s.userId=u.userId','left')
		            ->join('__STAFFS__ sf','sf.staffId=a.ownId','left')
		            ->where($where)->field('a.imgId,u.loginName,u.userType,fromType,sf.loginName loginName2,s.shopName,imgPath,imgSize,isUse,a.createTime')
		            ->order('a.imgId desc')->paginate(input('post.pagesize/d'))->toArray();
		foreach ($page['Rows'] as $key => $v){
			if($v['fromType']==1){
				$page['Rows'][$key]['loginName'] = $v['loginName2'];
			}
			$page['Rows'][$key]['imgSize'] = round($v['imgSize']/1024/1024,2);
			unset($page['Rows'][$key]['loginName2']);
		}
		return $page;
	}
	/**
	 * 删除图片
	 */
	public function del(){
		$id = (int)input('id');
		$image = $this->where('imgId',$id)->find();
		$rs = $this->where('imgId',$id)->update(['dataFlag'=>-1]);
		if(false !== $rs){
			$m = FIConf('CONF.fiMobileImgSuffix');
			$timgPath =  str_replace('.','_thumb.',$image['imgPath']);
			$mimgPath =  str_replace('.',$m.'.',$image['imgPath']);
	        $mtimgPath = str_replace('.',$m.'_thumb.',$image['imgPath']);
	        
			if(file_exists(FIRootPath()."/".$image['imgPath']))unlink(FIRootPath()."/".$image['imgPath']); 
			if(file_exists(FIRootPath()."/".$timgPath))unlink(FIRootPath()."/".$timgPath); 
			if(file_exists(FIRootPath()."/".$mimgPath))unlink(FIRootPath()."/".$mimgPath); 
			if(file_exists(FIRootPath()."/".$mtimgPath))unlink(FIRootPath()."/".$mtimgPath); 
			return FIReturn("删除成功", 1);
		}
		return FIReturn("删除失败");
	}
}
