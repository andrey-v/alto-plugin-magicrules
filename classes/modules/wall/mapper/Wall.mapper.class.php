<?php

/**
 * Topic.mapper.class.php
 * Файл маппера для модуля Topic плагина Magicrules
 *
 * @author      Андрей Воронов <andreyv@gladcode.ru>
 * @copyrights  Copyright © 2015, Андрей Воронов
 *              Является частью плагина Magicrules
 * @version     0.0.1 от 11.11.2015 11:01
 */
class PluginMagicrules_ModuleWall_MapperWall extends PluginMagicrules_Inherit_ModuleWall_MapperWall {

    /**
     * Возвращает количество топиков созданных пользователем за последние {{$iSecond}} секунд.
     * Учитываются только опубликованные топики
     *
     * @param ModuleUser_EntityUser $oUser
     * @param int $iSecond
     * @return int
     */
    public function GetCountUsersWallByTimeLast($oUser, $iSecond) {

        $sql = "SELECT
                  COUNT(id)
                FROM
                  ?_wall
                WHERE
                  user_id = ?d
                  AND date_add > DATE_SUB(NOW(), INTERVAL ?d SECOND)";

        return (int)$this->oDb->selectCell($sql, $oUser->getId(), $iSecond);

    }

    /**
     * Возвращает отрицательное значение секунд, означающее количество секунд до истечения
     * ограничения или положительное число секунд если ограничение истекло
     *
     * @param ModuleUser_EntityUser $oUser
     * @param $iSecond
     * @return int
     */
    public function CheckLastWallTime($oUser, $iSecond) {

        $sql = "SELECT
                  TIMESTAMPDIFF(SECOND, DATE_ADD(date_add, INTERVAL ?d SECOND), NOW())
                FROM
                  ?_wall
                WHERE
                  user_id = ?d
                ORDER BY
                  date_add DESC
                LIMIT 1";

        return (int)$this->oDb->selectCell($sql, $iSecond, $oUser->getId());
    }

}