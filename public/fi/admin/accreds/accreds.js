var grid;
function initGrid(){
	grid = $("#maingrid").ligerGrid({
		url:FI.U('admin/Accreds/pageQuery'),
		pageSize:FI.pageSize,
		pageSizeOptions:FI.pageSizeOptions,
		height:'99%',
        width:'100%',
        minColToggle:6,
        rownumbers:true,
        columns: [
	        { display: '图标', name: 'accredImg', isSort: false,render:function(rowdata, rowindex, value){
	        	return '<img src="'+FI.conf.ROOT+'/'+rowdata['accredImg']+'" height="28px" />';
	        }},
	        { display: '认证名称', name: 'accredName', isSort: false},
	        { display: '创建时间', name: 'createTime', isSort: false},
	        { display: '操作', name: 'op',isSort: false,render: function (rowdata, rowindex, value){
	        	var h="";
	            if(FI.GRANT.RZGL_02)h += "<a href='javascript:getForEdit(" + rowdata['accredId'] + ")'>修改</a> ";
	            if(FI.GRANT.RZGL_03)h += "<a href='javascript:toDel(" + rowdata['accredId'] + ")'>删除</a> "; 
	            return h;
	        }}
        ]
    });
}


function getForEdit(id){
	 var loading = FI.msg('正在获取数据，请稍后...', {icon: 16,time:60000});
     $.post(FI.U('admin/accreds/get'),{id:id},function(data,textStatus){
           layer.close(loading);
           var json = FI.toAdminJson(data);
           if(json.accredId){
           		FI.setValues(json);
           		//显示原来的图片
           		$('#preview').html('<img src="'+FI.conf.ROOT+'/'+json.accredImg+'" height="70px" />');
           		$('#isImg').val('ok');
           		toEdit(json.accredId);
           }else{
           		FI.msg(json.msg,{icon:2});
           }
    });
}

function toEdit(id){
	var title =(id==0)?"新增":"编辑";
	var box = FI.open({title:title,type:1,content:$('#accredBox'),area: ['450px', '280px'],btn: ['确定','取消'],yes:function(){
			$('#accredForm').submit();
	},cancel:function(){
		//重置表单
		$('#accredForm')[0].reset();
		//清空预览图
		$('#preview').html('');
		$('#accredImg').val('');

	},end:function(){
		//重置表单
		$('#accredForm')[0].reset();
		//清空预览图
		$('#preview').html('');
		$('#accredImg').val('');

	}});
	$('#accredForm').validator({
        fields: {
            accredName: {
            	rule:"required;",
            	msg:{required:"请输入认证名称"},
            	tip:"请输入认证名称",
            	ok:"",
            },
            accredImg:  {
            	rule:"required;",
            	msg:{required:"请上传图标"},
            	tip:"请上传图标",
            	ok:"",
            },
            
        },
       valid: function(form){
		        var params = FI.getParams('.ipt');
		        	params.accredId = id;
		        var loading = FI.msg('正在提交数据，请稍后...', {icon: 16,time:60000});
		   		$.post(FI.U('admin/accreds/'+((id==0)?"add":"edit")),params,function(data,textStatus){
		   			  layer.close(loading);
		   			  var json = FI.toAdminJson(data);
		   			  if(json.status=='1'){
		   			    	FI.msg("操作成功",{icon:1});
		   			    	$('#accredForm')[0].reset();
		   			    	//清空预览图
		   			    	$('#preview').html('');
		   			    	//清空图片隐藏域
		   			    	$('#accredImg').val('');
		   			    	layer.close(box);
		   		            grid.reload();
		   			  }else{
		   			        FI.msg(json.msg,{icon:2});
		   			  }
		   		});

    	}

  });
}

$(function(){
//文件上传
FI.upload({
    pick:'#adFilePicker',
    formData: {dir:'accreds'},
    accept: {extensions: 'gif,jpg,jpeg,bmp,png',mimeTypes: 'image/*'},
    callback:function(f){
      var json = FI.toAdminJson(f);
      if(json.status==1){
        $('#uploadMsg').empty().hide();
        //将上传的图片路径赋给全局变量
	    $('#accredImg').val(json.savePath+json.thumb);
	    $('#preview').html('<img src="'+FI.conf.ROOT+'/'+json.savePath+json.thumb+'" height="75" />');
      }else{
      	FI.msg(json.msg,{icon:2});
      }
  },
  progress:function(rate){
      $('#uploadMsg').show().html('已上传'+rate+"%");
  }
});

});




function toDel(id){
	var box = FI.confirm({content:"您确定要删除该记录吗?",yes:function(){
	           var loading = FI.msg('正在提交数据，请稍后...', {icon: 16,time:60000});
	           	$.post(FI.U('admin/Accreds/del'),{id:id},function(data,textStatus){
	           			  layer.close(loading);
	           			  var json = FI.toAdminJson(data);
	           			  if(json.status=='1'){
	           			    	FI.msg("操作成功",{icon:1});
	           			    	layer.close(box);
	           		            grid.reload();
	           			  }else{
	           			    	FI.msg(json.msg,{icon:2});
	           			  }
	           		});
	            }});
}






		