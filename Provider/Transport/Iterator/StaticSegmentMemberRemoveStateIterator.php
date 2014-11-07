<?php

namespace OroCRM\Bundle\MailChimpBundle\Provider\Transport\Iterator;

use Oro\Bundle\BatchBundle\ORM\Query\BufferedQueryResultIterator;
use OroCRM\Bundle\MailChimpBundle\Entity\StaticSegment;
use OroCRM\Bundle\MailChimpBundle\Entity\StaticSegmentMember;

class StaticSegmentMemberRemoveStateIterator extends AbstractStaticSegmentIterator
{
    /**
     * @var string
     */
    protected $segmentMemberClassName;

    /**
     * @param string $segmentMemberClassName
     */
    public function setSegmentMemberClassName($segmentMemberClassName)
    {
        $this->segmentMemberClassName = $segmentMemberClassName;
    }

    /**
     * @param StaticSegment $staticSegment
     *
     * @return \Iterator|BufferedQueryResultIterator
     */
    protected function createSubordinateIterator($staticSegment)
    {
        if (!$this->segmentMemberClassName) {
            throw new \InvalidArgumentException('StaticSegmentMember class name must be provided');
        }

        if (!$marketingList = $staticSegment->getMarketingList()) {
            return new \ArrayIterator();
        }

        $qb = $this
            ->getIteratorQueryBuilder($marketingList)
            ->select(self::MEMBER_ALIAS . '.id');

        $segmentMembersQb = clone $qb;
        $segmentMembersQb
            ->resetDQLParts()
            ->select(
                [
                    'staticSegment.id static_segment_id',
                    'smmb.id member_id',
                    $segmentMembersQb->expr()->literal(StaticSegmentMember::STATE_REMOVE) . ' state'
                ]
            )
            ->from($this->segmentMemberClassName, 'segmentMember')
            ->join('segmentMember.member', 'smmb')
            ->join('segmentMember.staticSegment', 'staticSegment')
            ->andWhere($qb->expr()->eq('staticSegment.id', $staticSegment->getId()))
            ->andWhere($segmentMembersQb->expr()->notIn('smmb.id', $qb->getDQL()));

        return new BufferedQueryResultIterator($segmentMembersQb);
    }
}