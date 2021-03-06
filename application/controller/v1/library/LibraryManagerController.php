<?php

/*
 * 文档库管理相关 Controller
 *
 * @Created: 2020-06-21 10:20:43
 * @Author: yesc (yes.ccx@gmail.com)
 */

namespace app\controller\v1\library;

use app\constants\module\LibraryMemberOperateCode;
use app\extend\common\AppPagination;
use app\extend\common\AppQuery;
use app\extend\library\LibraryMemberOperate;
use app\kernel\base\AppBaseController;
use app\logic\libraryManager\LibraryMemberInviteLogic;
use app\logic\libraryManager\LibraryMemberLibrarySortLogic;
use app\logic\libraryManager\LibraryMemberRoleModifyLogic;
use app\logic\libraryManager\LibraryMemberStatusModifyLogic;
use app\logic\libraryManager\LibraryMemberUninviteLogic;
use app\service\library\LibraryManagerService;
use app\service\library\LibraryService;
use app\service\library\LibraryShareService;
use think\Db;

class LibraryManagerController extends AppBaseController {

    protected $middleware = [
        \app\kernel\middleware\library\LibraryAuthMiddleware::class             => [ // 文档库操作鉴权
            'only' => [
                'libraryManagerInfo', 'libraryMemberLibrarySort', 'libraryMemberCollection', 'libraryMemberInvite',
                'libraryMemberUninvite', 'libraryMemberStatusModify', 'libraryMemberRoleModify', 'libraryShareList',
            ],
        ],
        \app\kernel\middleware\library\LibraryManagerShareAuthMiddleware::class => [
            'only' => ['libraryShareStatusModify', 'libraryShareRemove'],
        ],
    ];

    /**
     * 文档库管理信息
     */
    public function libraryManagerInfo() {
        $libraryId = $this->request->libraryId;

        $libraryInfo = LibraryService::getLibraryInfo($libraryId, 'id,uid,team_id,name,desc,create_time,update_time,cover');
        $libraryMember = LibraryService::getLibraryMemberInfo($libraryId, $this->uid, 'group_id,uid,urole,apply_time');

        return $this->responseData([
            'libraryInfo'   => $libraryInfo,
            'libraryMember' => $libraryMember,
        ]);
    }

    /**
     * 成员文档库排序
     */
    public function libraryMemberLibrarySort() {
        $libraryId = $this->request->libraryId;
        $libraryGroupId = $this->input('library_group_id/d', 0);
        $sort = $this->input('sort/f', -1);
        if ($libraryGroupId < 0) {
            return $this->responseError('参数错误');
        }

        $memberLibrarySort = LibraryMemberLibrarySortLogic::make();
        Db::transaction(function () use ($memberLibrarySort, $libraryId, $libraryGroupId, $sort) {
            $memberLibrarySort->useLibraryMember($this->uid, $libraryId)->useLibraryGroup($libraryGroupId, $sort)->sort();
        });

        return $this->responseSuccess('修改成功');
    }

    /**
     * 文档库成员集合
     */
    public function libraryMemberCollection() {
        $collection = LibraryManagerService::getLibraryMemberCollection(
            $this->request->libraryId, 'id,library_id,uid,urole,status,apply_time,create_time,update_time'
        );
        return $this->responseData($collection);
    }

    /**
     * 文档库邀请用户
     * // TODO: 文档库邀请多个用户时，代码实现待优化（不能用foreach）
     */
    public function libraryMemberInvite() {
        LibraryMemberOperate::checkOperate(LibraryMemberOperateCode::LIBRARY_MEMBER__INVITE);

        $libraryId = $this->request->libraryId;
        $memberIds = $this->input('member_ids/a', []);
        if (empty($memberIds)) {
            return $this->responseError('存在无效的用户');
        }

        $memberInvite = LibraryMemberInviteLogic::make();
        Db::transaction(function () use ($memberInvite, $libraryId, $memberIds) {
            foreach ($memberIds as $memberId) {
                $memberInvite->useLibraryMember($memberId, $libraryId)->invite();
            }
        });

        return $this->responseSuccess('ok');
    }

    /**
     * 文档库成员移除
     */
    public function libraryMemberUninvite() {
        LibraryMemberOperate::checkOperate(LibraryMemberOperateCode::LIBRARY_MEMBER__REMOVE);

        $libraryId = $this->request->libraryId;
        $memberId = $this->input('member_id/d', 0);
        if ($memberId <= 0) {
            return $this->responseError('参数错误');
        }

        $memberUninvite = LibraryMemberUninviteLogic::make();
        Db::transaction(function () use ($memberUninvite, $libraryId, $memberId) {
            $memberUninvite->useLibraryMember($memberId, $libraryId)->uninvite();
        });

        return $this->responseSuccess('移除成功');
    }

    /**
     * 文档库成员状态调整
     */
    public function libraryMemberStatusModify() {
        LibraryMemberOperate::checkOperate(LibraryMemberOperateCode::LIBRARY_MEMBER__STATUS_MODIFY);

        $libraryId = $this->request->libraryId;
        $memberId = $this->input('member_id/d', 0);
        $status = $this->input('status/d', 0);
        if ($memberId <= 0) {
            return $this->responseError('参数错误');
        }

        $memberStatusModify = LibraryMemberStatusModifyLogic::make();
        Db::transaction(function () use ($memberStatusModify, $libraryId, $memberId, $status) {
            $memberStatusModify->useLibraryMember($memberId, $libraryId)->useMemberStatus($status)->modify();
        });

        return $this->responseSuccess('修改成功');
    }

    /**
     * 文档库成员角色调整
     */
    public function libraryMemberRoleModify() {
        LibraryMemberOperate::checkOperate(LibraryMemberOperateCode::LIBRARY_MEMBER__ROLE_MODIFY);

        $libraryId = $this->request->libraryId;
        $memberId = $this->input('member_id/d', 0);
        $libraryRoleId = $this->input('library_role_id/d', 0);
        if ($memberId <= 0 || $libraryRoleId <= 0) {
            return $this->responseError('参数错误');
        }

        $memberRoleModify = LibraryMemberRoleModifyLogic::make();
        Db::transaction(function () use ($memberRoleModify, $libraryId, $memberId, $libraryRoleId) {
            $memberRoleModify->useLibraryMember($memberId, $libraryId)->useLibraryMemberRole($libraryRoleId)->modify();
        });

        return $this->responseSuccess('修改成功');
    }

    /**
     * 文档库分享列表
     */
    public function libraryShareList(AppPagination $pagination) {
        $searchKey = $this->input('search_key/s', '');
        $searchCreateStart = $this->input('search_create_start/d', 0);
        $searchCreateEnd = $this->input('search_create_end/d', 0);

        $query = AppQuery::make()
            ->field('id,library_id,doc_id,uid,share_name,share_code,is_protected,access_count,create_time,expire_time,status');
        if (empty($pagination->pageOrder)) {
            $query->order('id', 'desc');
        }

        // 查询条件
        $query->when($searchKey, function ($squery) use ($searchKey) {
            $squery->whereLike('share_name', "%{$searchKey}%");
        })->when($searchCreateStart, function ($squery) use ($searchCreateStart) {
            $squery->where('create_time', '>=', $searchCreateStart);
        })->when($searchCreateEnd, function ($squery) use ($searchCreateEnd) {
            $squery->where('create_time', '<=', $searchCreateEnd + 86399);
        });

        $collection = LibraryManagerService::getLibraryShareList(
            $this->request->libraryId, $query, $pagination
        );
        return $this->responseData($collection);
    }

    /**
     * 文档库分享状态更改
     */
    public function libraryShareStatusModify() {
        LibraryMemberOperate::checkOperate(LibraryMemberOperateCode::LIBRARY_SHARE__STATUS_MODIFY);

        $libraryShareId = $this->request->libraryShareId;
        $status = $this->input('status/d', 0);
        if (!in_array($status, [1, 2])) {
            return $this->responseError('状态值错误');
        }

        $modifyRes = LibraryShareService::modifyLibraryShareStatus($libraryShareId, $status);
        if (empty($modifyRes)) {
            return $this->responseError('修改失败');
        }

        return $this->responseSuccess('修改成功');
    }

    /**
     * 文档库分享删除
     */
    public function libraryShareRemove() {
        LibraryMemberOperate::checkOperate(LibraryMemberOperateCode::LIBRARY_SHARE__REMOVE);

        $libraryShareId = $this->request->libraryShareId;
        LibraryShareService::removeLibraryShare($libraryShareId);

        return $this->responseSuccess('删除成功');
    }

}