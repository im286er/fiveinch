<?php
namespace fi\admin\model;
use think\Db;
/**
 * 订单投诉业务处理
 */
class OrderComplains extends Base{
	/**
	 * 获取订单投诉列表
	 */
	public function pageQuery(){
		$shopName = Input('shopName');
     	$orderNo = Input('orderNo');

     	$complainStatus = (int)Input('complainStatus',-1);
     	
     	// 搜素条件
     	$areaId1 = (int)input('areaId1');
		if($areaId1>0){
			$where['s.areaIdPath'] = ['like',"$areaId1%"];
			$areaId2 = (int)input("areaId1_".$areaId1);
			if($areaId2>0)$where['s.areaIdPath'] = ['like',$areaId1."_"."$areaId2%"];
			$areaId3 = (int)input("areaId1_".$areaId1."_".$areaId2);
			if($areaId3>0)$where['s.areaId'] = $areaId3;
		}

	 	if($complainStatus>-1)$where['oc.complainStatus']=$complainStatus;
	 	if($orderNo!='')$where['o.orderNo']=['like',"%$orderNo%"];


     	$where['o.dataFlag']=1;
		$rs = Db::table('__ORDERS__')->alias('o')
							  ->field('oc.complainId,o.orderId,o.orderNo,s.shopName,u.userName,u.loginName,oc.complainTime,oc.complainStatus,oc.complainType')
						      ->join('__SHOPS__ s','o.shopId=s.shopId','inner','left')
						      ->join('__USERS__ u','o.userId=u.userId','inner')
						      ->join('__ORDER_COMPLAINS__ oc','oc.orderId=o.orderId','inner')
						      ->where($where)
						      ->order('complainId desc')
						      ->paginate()
						      ->toArray();
		return $rs;
	}

	/**
	 * 获取订单详细信息
	 */
	 public function getDetail(){
	 	$complainId = (int)Input('cid');
	 	$data = $this->alias('oc')
	 				 ->field('oc.*,u.userName,u.loginName')
	 				 ->join('__USERS__ u','oc.complainTargetId=u.userId','inner')
	 				 ->where("oc.complainId=$complainId")
	 				 ->find();
	 	if($data){
	 		if($data['complainAnnex']!='')$data['complainAnnex'] = explode(',',$data['complainAnnex']);
	 		if($data['respondAnnex']!='')$data['respondAnnex'] = explode(',',$data['respondAnnex']);
			$data['userName'] = ($data['userName']=='')?$data['loginName']:$data['userName'];
		 	$rs = Db::table('__ORDERS__')->alias('o')
		 					  ->field('o.orderStatus,o.areaId,o.userAddress,o.orderNo,o.userName,s.shopName,o.userAddress')
		 					  ->join('__SHOPS__ s','o.shopId=s.shopId','left')
		 					  ->where(['o.dataFlag'=>1,
		 					  		   'o.orderId'=>$data['orderId']])
		 					  ->find();
			//获取日志信息
			$rs['log'] = Db::table('__LOG_ORDERS__')->alias('lo')
										  ->field('lo.*,u.loginName,u.userType,s.shopName')
									      ->join('__USERS__ u','lo.logUserId=u.userId','left')
									      ->join('__SHOPS__ s','u.userType!=0 and s.userId=u.userId','left')
									      ->where(['orderId'=>$data['orderId']])
									      ->select();
			//获取相关商品
			$rs['goodslist'] = Db::name('order_goods')->where(['orderId'=>$data['orderId']])->select();
			$data['order'] = $rs;
	 	}
		return $data;
	 }

	 /**
	  * 转交给应诉人应诉
	  */
	 public function deliverRespond(){
	 	$id = (int)Input('id');
	 	if($id==0){
	 		return FIReturn('无效的投诉信息',-1);
	 	}
	 	//判断是否已经处理过了
	 	$rs = $this->alias('oc')
	 			   ->field('oc.complainStatus,oc.respondTargetId,o.orderNo,s.userId')
	 			   ->join('__ORDERS__ o','oc.orderId=o.orderId','inner')
	 			   ->join('__SHOPS__ s','o.shopId = s.shopId','left')
	 			   ->where("complainId=$id")
	 			   ->find();
	 	if($rs['complainStatus']==0){
	 		$data = array();
	 		$data['needRespond'] = 1;
	 		$data['complainStatus'] = 1;
	 		$data['deliverRespondTime'] = date('Y-m-d H:i:s');
	 		Db::startTrans();
		    try{
		 	    $ers = $this->where('complainId='.$id)->update($data);
		 	    if($ers!==false){
			 	    //发站内信息提醒
		 	    	FISendMsg($rs['userId'],"您有新的被投诉订单【".$rs['orderNo']."】，请及时回应以免影响您的店铺评分。",['from'=>3,'dataId'=>$id]);
					Db::commit();
					return FIReturn('操作成功',1);
		 	    }
		    }catch (\Exception $e) {
	            Db::rollback();
	            return FIReturn('操作失败',-1);
	        }
	 	}else{
	 	    return FIReturn('操作失败，该投诉状态已发生改变，请刷新后重试!',-1);
	 	}
	 	return $rd;
	 }

	 /**
	  * 仲裁
	  */
	 public function finalHandle(){
	 	$rd = array('status'=>-1,'msg'=>'无效的投诉信息');
	 	$complainId = (int)Input('cid');
	 	if($complainId==0){
	 		return FIReturn('无效的投诉信息',-1);
	 	}
	 	if(!in_array((int)Input("orderStatus",0),[0,-4,-5])){
	 		return FIReturn('无效的订单处理结果',-1);
	 	}
	 	//判断是否已经处理过了
	 	$rs = $this->alias('oc')
	 			   ->field('oc.complainStatus,s.userId shopUserId,o.shopId,o.userId,o.orderNo,o.orderId,o.orderStatus,o.orderScore,oc.needRespond')
	 			   ->join('__ORDERS__ o','oc.orderId=o.orderId','inner')
	 			   ->join('__SHOPS__ s','o.shopId=s.shopId','left')
	 			   ->where("complainId=$complainId")
	 			   ->find();
	 	if($rs['complainStatus']!=4){
	 		$data = array();
	 		$data['finalHandleStaffId'] = session('FI_STAFF.staffId');
	 		$data['complainStatus'] = 4;
	 		$data['finalResult'] = Input('finalResult');
	 		$data['finalResultTime'] = date('Y-m-d H:i:s');
	 		Db::startTrans();
		    try{
	 	        $ers = $this->where('complainId='.$complainId)->update($data);
	 	        if($ers!==false){
	 	        	//需要卖家回应的话则给卖家也一条消息
		 	    	if($rs['needRespond']==1){
		 	    		//发站内商家信息提醒
		 	    		FISendMsg($rs['shopUserId'],"您的被投诉订单【".$rs['orderNo']."】已仲裁，请查看订单投诉详情。",['from'=>3,'dataId'=>$complainId]);
		 	    	}
					//发站内用户信息提醒
		 	    	FISendMsg($rs['userId'],"您的订单投诉【".$rs['orderNo']."】已仲裁，请查看订单投诉详情。",['from'=>3,'dataId'=>$complainId]);
                    //处理订单状态
					$orderId = $rs['orderId'];
					$userId = $rs['userId'];
					$orderStatus = (int)Input("orderStatus",0);
                    //增加订单日志
                    $complainTxt = '';
                    if($orderStatus==0){
                    	$complainTxt = '订单仲裁结果：维持现状';
                    }else{
                    	$complainTxt = ($orderStatus==-4)?"订单仲裁结果：同意用户拒收":"订单仲裁结果：不同意用户拒收";
                    }
					$data = array();
					$data["orderId"] = $orderId;
					$data["orderStatus"] = $orderStatus;
					$data["logContent"] = ($orderStatus==-4)?"订单仲裁结果：同意用户拒收":"订单仲裁结果：不同意用户拒收";
					$data["logUserId"] = $rs['userId'];
					$data["logType"] = 0;
					$data["logTime"] = date('Y-m-d H:i:s');
					Db::name('log_orders')->insert($data);
					//根据仲裁结果，修改订单状态
					if($orderStatus!=0){
					    model('orders')->where("orderId=$orderId")->setField('orderStatus',$orderStatus);
					}
					Db::commit();
					return FIReturn('操作成功',1);
	 	        }
	 	    }catch(\Exception $e){
	 	    	Db::rollback();
	            return FIReturn('操作失败',-1);
	 	    }
	 	}else{
	 	    return FIReturn('操作失败，该投诉状态已发生改变，请刷新后重试!',-1);
	 	}

	 }
}
