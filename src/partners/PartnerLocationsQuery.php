<?php

namespace craftnet\partners;


use craft\db\Query;

class PartnerLocationsQuery extends Query
{
    private $_partnerId;
    private $_id;

    public function id($id): Query
    {
        $this->_id = $id;

        return $this;
    }

    public function partner($partner): Query
    {
        $this->_partnerId = is_numeric($partner) ? $partner : $partner->id;

        return $this;
    }

    /**
     * @param \yii\db\QueryBuilder $builder
     * @return $this|Query
     */
    public function prepare($builder)
    {
        $this
            ->select('*')
            ->from('craftnet_partnerlocations l');

        if (isset($this->_partnerId)) {
            $this->where(['l.partnerId' => $this->_partnerId]);
        }

        return $this;
    }
}
