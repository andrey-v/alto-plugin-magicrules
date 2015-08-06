<?php
/* ---------------------------------------------------------------------------
 * @Project: Alto CMS
 * @Plugin Name: Magic Rules
 * @Description: Specific rules for voting and add content
 * @Author: Alto CMS Team
 * @Author URI: http://altocms.com
 * @License: GNU GPL v2
 *----------------------------------------------------------------------------
 * Based on
 *   Plugin Magic Rule for LiveStreet CMS
 *   Author: LiveStreet Developers Team
 *   Site: https://github.com/livestreet/lsplugin-magicrule
 *----------------------------------------------------------------------------
 */

class PluginMagicrules_ModuleRule extends ModuleORM {

    const BLOCK_TYPE_VOTE = 1;
    const BLOCK_TYPE_CREATE = 2;

    /**
     * Список направлений голосований и их синонимы
     *
     * @var array
     */
    protected $aVoteMirrow = array(1 => 'up', -1 => 'down', 0 => 'abstain');

    /**
     * Объект маппера
     *
     * @var  PluginMagicrules_ModuleRule_MapperRule
     */
    protected $oMapper;

    /**
     * Инициализация
     *
     */
    public function Init() {

        parent::Init();
        $this->oMapper = Engine::GetMapper(__CLASS__);
    }

    protected function _text($sText) {

        $sText = (string)$sText;
        if ($sText && substr($sText, 0, 2) == '{{' && substr($sText, -2) == '}}') {
            $sText = E::ModuleLang()->Get('plugin.magicrules.' . substr($sText, 2, strlen($sText) - 4));
        }
        return $sText;
    }

    /**
     * @param string                $sAction
     * @param ModuleUser_EntityUser $oUser
     * @param array                 $aParams
     *
     * @return bool|string
     */
    public function CheckRuleAction($sAction, $oUser, $aParams = array()) {

        if ($oUser->isAdministrator()) {
            return true;
        }

        // * Проверка на наличие блокировок
        list($iBlockType, $sBlockTarget) = $this->GetTypeAndTargetByAction($sAction);
        $xResult = $this->CheckRuleBlock($iBlockType, $sBlockTarget, $oUser, $aParams);

        if (true !== $xResult) {
            return $xResult ? $xResult : false;
        }

        // * Проверка на запрещающие правила
        $bSkip = false;
        $aType = (array)Config::Get('plugin.magicrules.rule_disallow.' . $sAction . '.type');
        if ($iBlockType == self::BLOCK_TYPE_VOTE
            && isset($aParams['vote_value'])
            && count($aType)
            && !in_array($this->aVoteMirrow[$aParams['vote_value']], $aType)
        ) {
            $bSkip = true;
        }
        $aGroups = (array)Config::Get('plugin.magicrules.rule_disallow.' . $sAction . '.groups');
        if (!$bSkip && count($aGroups)) {
            $sMsg = $this->_text(Config::Get('plugin.magicrules.rule_disallow.' . $sAction . '.msg'));
            foreach ($aGroups as $aRule) {
                $bCheck = true;
                foreach ($aRule as $sOption => $xValue) {
                    if (!$this->CheckRuleDisallowActionParam($sOption, $xValue, $oUser, $aParams)) {
                        $bCheck = false;
                        break;
                    }
                }
                if ($bCheck) {
                    return $sMsg ? $sMsg : false;
                }
            }
        }

        // * Проверка на разрешающие правила
        $aGroups = (array)Config::Get('plugin.magicrules.rule_allow.' . $sAction . '.groups');
        if (!count($aGroups)) {
            return true;
        }
        $sMsg = $this->_text(Config::Get('plugin.magicrules.rule_allow.' . $sAction . '.msg'));

        foreach ($aGroups as $aRule) {
            $bCheck = true;
            foreach ($aRule as $sOption => $xValue) {
                if (!$this->CheckRuleActionParam($sOption, $xValue, $oUser, $aParams)) {
                    $bCheck = false;
                    break;
                }
            }
            if ($bCheck) {
                return true;
            }
        }
        return $sMsg ? $sMsg : false;
    }

    /**
     * @param string $sAction
     *
     * @return array
     */
    public function GetTypeAndTargetByAction($sAction) {

        $aPath = explode('_', strtolower($sAction));
        if (isset($aPath[0]) && isset($aPath[1])) {
            $iBlockType = null;
            if ($aPath[0] == 'vote') {
                $iBlockType = self::BLOCK_TYPE_VOTE;
            } elseif ($aPath[0] == 'create') {
                $iBlockType = self::BLOCK_TYPE_CREATE;
            }
            return array($iBlockType, $aPath[1]);
        }
        return array(null, null);
    }

    /**
     * @param int                   $iType
     * @param string                $sTarget
     * @param ModuleUser_EntityUser $oUser
     * @param array                 $aParams
     *
     * @return bool
     */
    public function CheckRuleBlock($iType, $sTarget, $oUser, $aParams = array()) {

        $aBlockItems = $this->GetBlockItemsByFilter(
            array(
                'user_id' => $oUser->getId(),
                'type' => $iType,
                'target' => $sTarget,
                'date_block >=' => date('Y-m-d H:i:s'),
            )
        );

        // * Проверяем все действующие блокировки
        foreach ($aBlockItems as $oBlock) {
            // * Проверяем на направление голосования
            if ($iType == self::BLOCK_TYPE_VOTE) {
                if (isset($aParams['vote_value']) && $aDirection = $oBlock->getData('direction')) {

                    // Если нужного направления голосования нет в списке,
                    // то пропускаем блокировку
                    if (!in_array($aParams['vote_value'], $aDirection)) {
                        continue;
                    }
                }
            }
            if ($oBlock->getMsg()) {
                return $oBlock->getMsg();
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string                $sParam
     * @param mixed                 $xValue
     * @param ModuleUser_EntityUser $oUser
     * @param array                 $aParams
     *
     * @return bool
     */
    public function CheckRuleActionParam($sParam, $xValue, $oUser, $aParams = array()) {

        if ($sParam == 'registration_time') {
            if (time() - strtotime($oUser->getDateRegister()) >= $xValue) {
                return true;
            } else {
                return false;
            }
        }
        if ($sParam == 'rating') {
            if ($oUser->getRating() >= $xValue) {
                return true;
            } else {
                return false;
            }
        }
        if ($sParam == 'skill') {
            if ($oUser->getSkill() >= $xValue) {
                return true;
            } else {
                return false;
            }
        }
        if ($sParam == 'count_comment') {
            if (E::ModuleComment()->GetCountCommentsByUserId($oUser->getId(), 'topic') >= $xValue) {
                return true;
            } else {
                return false;
            }
        }
        if ($sParam == 'count_topic') {
            if (E::ModuleTopic()->GetCountTopicsPersonalByUser($oUser->getId(), 1) >= $xValue) {
                return true;
            } else {
                return false;
            }
        }
        if ($sParam == 'rating_sum_topic') {
            if (is_array($xValue) && count($xValue) > 1) {
                $iRating = $xValue[0];
                $iTime = $xValue[1];
            } else {
                $iRating = $xValue;
                $iTime = 60 * 60 * 24 * 14;
            }
            if ($this->GetSumRatingTopic($oUser->getId(), date('Y-m-d H:i:s', time() - $iTime)) >= $iRating) {
                return true;
            } else {
                return false;
            }
        }
        if ($sParam == 'rating_sum_comment') {
            if (is_array($xValue) && count($xValue) > 1) {
                $iRating = $xValue[0];
                $iTime = $xValue[1];
            } else {
                $iRating = $xValue;
                $iTime = 60 * 60 * 24 * 7;
            }
            if ($this->GetSumRatingComment($oUser->getId(), date('Y-m-d H:i:s', time() - $iTime)) >= $iRating) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * @param string                $sParam
     * @param mixed                 $xValue
     * @param ModuleUser_EntityUser $oUser
     * @param array                 $aParams
     *
     * @return bool
     */
    public function CheckRuleDisallowActionParam($sParam, $xValue, $oUser, $aParams = array()) {

        if ($sParam == 'user_id') {
            if (!is_array($xValue)) {
                $xValue = array($xValue);
            }
            if (in_array($oUser->getId(), $xValue)) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * @param ModuleVote_EntityVote $oVote
     *
     * @return bool
     */
    public function CheckForCreateBlockVote($oVote) {

        if (!($oUser = E::ModuleUser()->GetUserById($oVote->getVoterId()))) {
            return false;
        }
        $sTarget = $oVote->getTargetType();
        $sType = $this->aVoteMirrow[$oVote->getDirection()];

        $aGroups = (array)Config::Get('plugin.magicrules.rule_block_vote');
        foreach ($aGroups as $aRule) {
            if (!in_array($sTarget, $aRule['target'])) {
                continue;
            }
            if (!in_array($sType, $aRule['type'])) {
                continue;
            }
            if (isset($aRule['rating'])) {
                if ($oUser->getRating() >= $aRule['rating']) {
                    continue;
                }
            }

            $sDate = date('Y-m-d H:i:s', time() - $aRule['period']);
            $iCount = $this->GetCountVote($oUser->getId(), $sTarget, $sDate);
            if ($iCount >= $aRule['count']) {
                $oBlock = Engine::GetEntity('PluginMagicrules_ModuleRule_EntityBlock');
                $oBlock->setUserId($oUser->getId());
                $oBlock->setType(self::BLOCK_TYPE_VOTE);
                $oBlock->setName(isset($aRule['name']) ? $aRule['name'] : '');
                $oBlock->setTarget($sTarget);
                if (isset($aRule['block_msg'])) {
                    $sMsg = $this->_text($aRule['block_msg']);
                    $oBlock->setMsg($sMsg);
                }
                $oBlock->setDateBlock(date('Y-m-d H:i:s', time() + $aRule['block_time']));
                $oBlock->setData(
                    array('direction' => array_values(
                        array_intersect_key(
                            array_flip($this->aVoteMirrow),
                            array_flip($aRule['type'])
                        )
                    ))
                );
                $oBlock->Add();

                // * Прекращаем обход правил
                if (!Config::Get('plugin.magicrules.processing_block_rule_all')) {
                    break;
                }
            }
        }
    }

    /**
     * @param int    $iUserId
     * @param string $sTargetType
     * @param string $sDate
     *
     * @return int
     */
    public function GetCountVote($iUserId, $sTargetType, $sDate) {

        return $this->oMapper->GetCountVote($iUserId, $sTargetType, $sDate);
    }

    /**
     * @param int  $iUserId
     * @param null $sDate
     *
     * @return int
     */
    public function GetSumRatingTopic($iUserId, $sDate = null) {

        return $this->oMapper->GetSumRatingTopic($iUserId, $sDate);
    }

    /**
     * @param int  $iUserId
     * @param null $sDate
     *
     * @return int
     */
    public function GetSumRatingComment($iUserId, $sDate = null) {

        return $this->oMapper->GetSumRatingComment($iUserId, $sDate);
    }

}

// EOF