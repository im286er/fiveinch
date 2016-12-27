<?php
namespace fi\common\model;

use think\Db;

/**
 * 标签业务处理类
 */
class Tags extends Base
{
    /**
     * 获取指定商品
     */
    public function listGoods($type, $catId = 0, $num, $cache = 0)
    {
        $type = strtolower($type);
        if (strtolower($type) == 'history') {
            return $this->historyByGoods($num);
        } else {
            return $this->listByGoods($type, $catId, $num, $cache);
        }
    }
    /**
     * 浏览商品
     */
    public function historyByGoods($num)
    {
        $hids = $ids = cookie("history_goods");
        if (empty($ids)) {
            return [];
        }

        $where                = [];
        $where['isSale']      = 1;
        $where['goodsStatus'] = 1;
        $where['g.dataFlag']  = 1;
        $where['goodsId']     = ['in', $ids];
        $goods                = Db::table('__GOODS__')->alias('g')->join('__SHOPS__ s', 'g.shopId=s.shopId')
            ->where($where)->field('s.shopName,s.shopId,goodsId,goodsName,goodsImg,goodsSn,goodsStock,saleNum,shopPrice,marketPrice,isSpec,appraiseNum,visitNum')
            ->limit($num)
            ->select();
        $ids = [];
        foreach ($goods as $key => $v) {
            if ($v['isSpec'] == 1) {
                $ids[] = $v['goodsId'];
            }

        }
        if (!empty($ids)) {
            $specs = [];
            $rs    = Db::table('__GOODS_SPECS__ gs ')->where(['goodsId' => ['in', $ids], 'dataFlag' => 1])->order('id asc')->select();
            foreach ($rs as $key => $v) {
                $specs[$v['goodsId']] = $v;
            }
            foreach ($goods as $key => $v) {
                if (isset($specs[$v['goodsId']])) {
                    $goods[$key]['specs'] = $specs[$v['goodsId']];
                }

            }
        }
        $hGoods = [];
        foreach ($hids as $k => $v) {
            foreach ($goods as $k1 => $v1) {
                if ($v1['goodsId'] == $v) {
                    $hGoods[] = $v1;
                }

            }
        }
        return $hGoods;
    }
    /**
     * 推荐商品
     */
    public function listByGoods($type, $catId, $num, $cache = 0)
    {
        if (!in_array($type, [0, 1, 2, 3])) {
            return [];
        }

        $cacheData = cache('TAG_GOODS_' . $type . "_" . $catId . "_" . $num);
        if ($cacheData) {
            return $cacheData;
        }

        //检测是否有数据
        $types                  = ['recom' => 0, 'new' => 3, 'hot' => 1, 'best' => 2];
        $where                  = [];
        $where['r.dataSrc']     = 0;
        $where['g.isSale']      = 1;
        $where['g.goodsStatus'] = 1;
        $where['g.dataFlag']    = 1;
        $goods                  = [];
        if ($type != 'visit') {
            $where['r.dataType']   = $types[$type];
            $where['r.goodsCatId'] = $catId;
            $goods                 = Db::table('__GOODS__')->alias('g')->join('__RECOMMENDS__ r', 'g.goodsId=r.dataId')
                ->join('__SHOPS__ s', 'g.shopId=s.shopId')
                ->where($where)->field('s.shopName,s.shopId,g.goodsId,goodsName,goodsImg,goodsSn,goodsStock,saleNum,shopPrice,marketPrice,isSpec,appraiseNum,visitNum')
                ->order('r.dataSort asc')->limit($num)->select();
        }
        //判断有没有设置，如果没有设置的话则获取实际的数据
        if (empty($goods)) {
            $goodsCatIds = FIGoodsCatPath($catId);
            $types       = ['recom' => 'isRecom', 'new' => 'isNew', 'hot' => 'isHot', 'best' => 'isBest'];
            $order       = ['recom' => 'saleNum desc,goodsId asc',
                'new'                   => 'saleTime desc,goodsId asc',
                'hot'                   => 'saleNum desc,goodsId asc',
                'best'                  => 'saleNum desc,goodsId asc',
                'visit'                 => 'visitNum desc',
            ];

            $where                = [];
            $where['isSale']      = 1;
            $where['goodsStatus'] = 1;
            $where['g.dataFlag']  = 1;

            if ($type != 'visit') {
                $where[$types[$type]] = 1;
            }

            if (!empty($goodsCatIds)) {
                $where['g.goodsCatIdPath'] = ['like', implode('_', $goodsCatIds) . '_%'];
            }

            $goods = Db::table('__GOODS__')->alias('g')->join('__SHOPS__ s', 'g.shopId=s.shopId')
                ->where($where)->field('s.shopName,s.shopId,goodsId,goodsName,goodsImg,goodsSn,goodsStock,saleNum,shopPrice,marketPrice,isSpec,appraiseNum,visitNum')
                ->order($order[$type])->limit($num)->select();
        }
        $ids = [];
        foreach ($goods as $key => $v) {
            if ($v['isSpec'] == 1) {
                $ids[] = $v['goodsId'];
            }

        }
        if (!empty($ids)) {
            $specs = [];
            $rs    = Db::table('__GOODS_SPECS__ gs ')->where(['goodsId' => ['in', $ids], 'dataFlag' => 1])->order('id asc')->select();
            foreach ($rs as $key => $v) {
                $specs[$v['goodsId']] = $v;
            }
            foreach ($goods as $key => $v) {
                if (isset($specs[$v['goodsId']])) {
                    $goods[$key]['specs'] = $specs[$v['goodsId']];
                }

            }
        }
        cache('TAG_GOODS_' . $type . "_" . $catId . "_" . $num, $goods, $cache);
        return $goods;
    }

    /**
     * 获取广告位置
     */
    public function listAds($positionCode, $num, $cache = 0)
    {
        $cacheData = cache('TAG_ADS' . $positionCode);
        if ($cacheData) {
            return $cacheData;
        }

        $today = date('Y-m-d');
        $rs    = Db::table("__ADS__")->alias('a')
            ->join('__AD_POSITIONS__ ap', 'a.adPositionId= ap.positionId and ap.dataFlag=1', 'left')
            ->where("a.dataFlag=1 and ap.positionCode='" . $positionCode . "' and adStartDate<= '$today' and adEndDate>='$today'")
            ->field('adId,adName,adURL,adFile,positionWidth,positionHeight')
            ->order('adSort asc')->limit($num)->select();
        cache('TAG_ADS' . $positionCode, $rs, $cache);
        return $rs;
    }

    /**
     * 获取友情链接
     */
    public function listFriendlink($num, $cache = 0)
    {
        $cacheData = cache('TAG_FRIENDLINK');
        if ($cacheData) {
            return $cacheData;
        }

        $rs = Db::table("__FRIENDLINKS__")->where(["dataFlag" => 1])->order("friendlinkSort asc")->select();
        cache('TAG_FRIENDLINK', $rs, $cache);
        return $rs;
    }

    /**
     * 获取文章列表
     */
    public function listArticle($catId, $num, $cache = 0)
    {
        $cacheData = cache('TAG_ARTICLES_' . $catId . "_" . $num);
        if ($cacheData) {
            return $cacheData;
        }

        $rs = [];
        if ($catId == 'new') {
            $rs = $this->listByNewArticle($num, $cache);
        } else {
            $rs = $this->listByArticle($catId, $num, $cache);
        }
        cache('TAG_ARTICLES_' . $catId . "_" . $num, $rs, $cache);
        return $rs;
    }
    /**
     * 获取最新文章
     */
    public function listByNewArticle($num, $cache)
    {
        $cacheData = cache('TAG_NEW_ARTICLES');
        if ($cacheData) {
            return $cacheData;
        }

        $rs = Db::table('__ARTICLES__')->alias('a')->field('a.articleId,a.articleTitle')->join('__ARTICLE_CATS__ ac', 'a.catId=ac.catId', 'inner')
            ->where('a.catId<>7 and ac.parentId<>7 and a.dataFlag=1')->order('a.createTime', 'desc')->limit($num)->select();
        cache('TAG_NEW_ARTICLES', $rs, $cache);
        return $rs;
    }
    /**
     * 获取指定分类的文章
     */
    public function listByArticle($catId, $num, $cache)
    {
        $where             = [];
        $where['dataFlag'] = 1;
        $where['isShow']   = 1;
        if (is_array($catId)) {
            $where['catId'] = ['in', $catId];
        } else {
            $where['catId'] = $catId;
        }
        return Db::table('__ARTICLES__')->where($where)
            ->field("articleId, catId, articleTitle")->order('createTime desc')->limit($num)->select();
    }

    /**
     * 获取指定店铺商品
     */
    public function listShopGoods($type, $shopId, $num, $cache = 0)
    {
        $cacheData = cache('TAG_SHOP_GOODS_' . $type . "_" . $shopId);
        if ($cacheData) {
            return $cacheData;
        }

        if (!in_array($type, [0, 1, 2, 3])) {
            return [];
        }

        $types                = ['recom' => 'isRecom', 'new' => 'isNew', 'hot' => 'isHot', 'best' => 'isBest'];
        $order                = ['recom' => 'saleNum desc,goodsId asc', 'new' => 'saleTime desc,goodsId asc', 'hot' => 'saleNum desc,goodsId asc', 'best' => 'saleNum desc,goodsId asc'];
        $where                = [];
        $where['shopId']      = $shopId;
        $where['isSale']      = 1;
        $where['goodsStatus'] = 1;
        $where['dataFlag']    = 1;
        $where[$types[$type]] = 1;
        $goods                = Db::table('__GOODS__')
            ->where($where)->field('goodsId,goodsName,goodsImg,goodsSn,goodsStock,saleNum,shopPrice,marketPrice,isSpec,appraiseNum,visitNum')
            ->order($order[$type])->limit($num)->select();
        $ids = [];
        foreach ($goods as $key => $v) {
            if ($v['isSpec'] == 1) {
                $ids[] = $v['goodsId'];
            }

        }
        if (!empty($ids)) {
            $specs = [];
            $rs    = Db::table('__GOODS_SPECS__ gs ')->where(['goodsId' => ['in', $ids], 'dataFlag' => 1])->order('id asc')->select();
            foreach ($rs as $key => $v) {
                $specs[$v['goodsId']] = $v;
            }
            foreach ($goods as $key => $v) {
                if (isset($specs[$v['goodsId']])) {
                    $goods[$key]['specs'] = $specs[$v['goodsId']];
                }

            }
        }
        cache('TAG_SHOP_GOODS_' . $type . "_" . $shopId, $goods, $cache);
        return $goods;
    }
    /**
     * 获取店铺分类下的商品
     */
    public function listShopFloorGoods($catId, $shopId, $num, $cache = 0)
    {
        $cacheData = cache('TAG_SHOP_CAT_GOODS_' . $catId . "_" . $shopId);
        if ($cacheData) {
            return $cacheData;
        }

        $where                = [];
        $where['shopId']      = $shopId;
        $where['isSale']      = 1;
        $where['goodsStatus'] = 1;
        $where['dataFlag']    = 1;
        $where['shopCatId2']  = $catId;
        $goods                = Db::table('__GOODS__')
            ->where($where)->field('goodsId,goodsName,goodsImg,goodsSn,goodsStock,saleNum,shopPrice,marketPrice,isSpec,appraiseNum,visitNum')
            ->limit($num)->select();
        cache('TAG_SHOP_CAT_GOODS_' . $catId . "_" . $shopId, $goods, $cache);
        return $goods;
    }
}
