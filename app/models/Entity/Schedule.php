<?php
namespace Entity;

use \Doctrine\Common\Collections\ArrayCollection;

/**
 * @Table(name="schedule", indexes={
 *   @index(name="search_idx", columns={"guid", "start_time", "end_time"})
 * })
 * @Entity
 * 
 */
class Schedule extends \DF\Doctrine\Entity
{
    public function __construct()
    {
        $this->is_notified = false;
    }

    /**
     * @Column(name="id", type="integer")
     * @Id
     * @GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /** @Column(name="type", type="string", length=40, nullable=true) */
    protected $type;

    /** @Column(name="station_id", type="integer", nullable=true) */
    protected $station_id;

    /** @Column(name="guid", type="string", length=32, nullable=true) */
    protected $guid;

    /** @Column(name="start_time", type="integer") */
    protected $start_time;

    /** @Column(name="end_time", type="integer") */
    protected $end_time;

    public function getRange()
    {
        return self::getRangeText($this->start_time, $this->end_time, $is_all_day = FALSE);
    }

    /** @Column(name="is_all_day", type="boolean", nullable=true) */
    protected $is_all_day;

    /** @Column(name="title", type="string", length=400, nullable=true) */
    protected $title;

    /** @Column(name="location", type="string", length=400, nullable=true) */
    protected $location;

    /** @Column(name="body", type="text", nullable=true) */
    protected $body;

    /** @Column(name="image_url", type="string", length=150, nullable=true) */
    protected $image_url;

    /** @Column(name="web_url", type="string", length=250, nullable=true) */
    protected $web_url;

    /** @Column(name="is_notified", type="boolean") */
    protected $is_notified;

    /**
     * @ManyToOne(targetEntity="Station")
     * @JoinColumns({
     *   @JoinColumn(name="station_id", referencedColumnName="id", onDelete="CASCADE")
     * })
     */
    protected $station;

    /**
     * Static Functions
     */

    public static function getUpcomingConventions()
    {
        $em = self::getEntityManager();

        $start_timestamp = strtotime('-3 days');
        $end_timestamp = strtotime('+1 year');

        $events_raw = $em->createQuery('SELECT s FROM Entity\Schedule s WHERE (s.type = :type AND s.start_time <= :end AND s.end_time >= :start) ORDER BY s.start_time ASC')
            ->setParameter('type', 'convention')
            ->setParameter('start', $start_timestamp)
            ->setParameter('end', $end_timestamp)
            ->useResultCache(true, 1800, 'pvl_upcoming_conventions')
            ->getArrayResult();

        $conventions = array();
        foreach($events_raw as $convention)
        {
            $special_info = \PVL\ScheduleManager::formatName($convention['title']);
            $convention = array_merge($convention, $special_info);

            $conventions[] = $convention;
        }

        return $conventions;
    }

    public static function getCurrentEvent($station_id)
    {
        $em = self::getEntityManager();
        $events_now = $em->createQuery('SELECT s FROM '.__CLASS__.' s WHERE (s.type = :type AND s.station_id = :station_id AND s.start_time <= :current AND s.end_time >= :current) ORDER BY s.start_time ASC')
            ->setParameter('type', 'station')
            ->setParameter('station_id', $station_id)
            ->setParameter('current', time())
            ->useResultCache(true, 90, 'pvl_event_'.$station_id)
            ->getArrayResult();

        if ($events_now)
        {
            $event = $events_now[0];
            $event['range'] = self::getRangeText($event['start_time'], $event['end_time'], $event['is_all_day']);
            return $event;
        }

        return NULL;
    }

    public static function getUpcomingEvent($station_id, $threshold = 1800)
    {
        $start_timestamp = time();
        $end_timestamp = $start_timestamp+$threshold;

        $em = self::getEntityManager();
        $events_raw = $em->createQuery('SELECT s FROM '.__CLASS__.' s WHERE (s.type = :type AND s.station_id = :station_id AND s.start_time >= :start AND s.start_time < :end) ORDER BY s.start_time ASC')
            ->setParameter('type', 'station')
            ->setParameter('station_id', $station_id)
            ->setParameter('start', $start_timestamp)
            ->setParameter('end', $end_timestamp)
            ->useResultCache(true, 90, 'pvl_event_upcoming_'.$station_id)
            ->getArrayResult();

        if ($events_raw)
        {
            $event = $events_raw[0];

            $event['minutes_until'] = round(($event['start_time'] - time()) / 60);
            $event['range'] = self::getRangeText($event['start_time'], $event['end_time'], $event['is_all_day']);
            return $event;
        }

        return NULL;
    }

    public static function getEventsInRange($station_id, $start_timestamp, $end_timestamp)
    {
        $em = self::getEntityManager();
        $events_now = $em->createQuery('SELECT s FROM '.__CLASS__.' s WHERE (s.type = :type AND s.station_id = :station_id AND s.start_time <= :end AND s.end_time >= :start) ORDER BY s.start_time ASC')
            ->setParameter('type', 'station')
            ->setParameter('station_id', $station_id)
            ->setParameter('start', $start_timestamp)
            ->setParameter('end', $end_timestamp)
            ->getArrayResult();

        $events = array();
        foreach($events_now as $event)
        {
            $event['range'] = self::getRangeText($event['start_time'], $event['end_time'], $event['is_all_day']);
            $events[] = $event;
        }

        return $events;
    }

    public static function getRangeText($start_time, $end_time, $is_all_day = FALSE)
    {
        $current_date = date('Y-m-d');

        $starts_today = (date('Y-m-d', $start_time) == $current_date);
        $ends_today = (date('Y-m-d', $end_time) == $current_date);
        $dates_match = (date('Y-m-d', $start_time) == date('Y-m-d', $end_time));

        $is_now = ($start_time < time() && $end_time >= time());

        // Special case for "all day today".
        if ($is_all_day && $is_now)
        {
            $range_text = 'All Day Today';
        }
        else
        {
            if ($is_now)
            {
                $range_text = 'Now';
            }
            else
            {
                $range_text = '';

                if ($starts_today)
                    $range_text .= 'Today';
                else
                    $range_text .= date('D F j', $start_time);

                if (!$is_all_day)
                    $range_text .= ' '.date('g:ia', $start_time);
            }

            if ($start_time != $end_time)
            {
                if ($ends_today)
                {
                    $range_text .= ' to '.date('g:ia', $end_time);
                }
                else if ($is_all_day)
                {
                    $range_text .= ' to '.date('g:ia', $end_time);
                }
                else
                {
                    $range_text .= ' to ';
                    if (!$dates_match)
                        $range_text .= date('D F j', $end_time).' ';

                    $range_text .= date('g:ia', $end_time);
                }
            }
        }

        return $range_text;
    }

}