<?php
namespace Arzttermine\SabreDavBundle\SabreDav;

use Arzttermine\CalendarBundle\Entity\Calendar;
use Arzttermine\CalendarBundle\Entity\Event;
use Arzttermine\UserBundle\Entity\User;
use Sabre\CalDAV;
use Sabre\CalDAV\Backend\BackendInterface;
use Sabre\DAV\Exception;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class CalDavBackend implements BackendInterface
{
    /**
     * @var \Doctrine\ORM\EntityManager 
     */
    private $em;

    /**
     * @var \FOS\UserBundle\Model\UserManagerInterface
     */
    private $user_manager;

    /**
     * @var string
     */
    private $calendar_class;

    /**
     * @var string
     */
    private $calendarobjects_class;

    /**
     * List of CalDAV properties, and how they map to database fieldnames
     * Add your own properties by simply adding on to this array.
     *
     * Note that only string-based properties are supported here.
     *
     * @var array
     */
    public $propertyMap = [
        '{DAV:}displayname'                                   => 'displayname',
        '{urn:ietf:params:xml:ns:caldav}calendar-description' => 'description',
        '{urn:ietf:params:xml:ns:caldav}calendar-timezone'    => 'timezone',
        '{http://apple.com/ns/ical/}calendar-order'           => 'calendarorder',
        '{http://apple.com/ns/ical/}calendar-color'           => 'calendarcolor',
    ];

    /**
     * Constructor
     *
     * @param \Doctrine\ORM\EntityManager $em 
     */
    public function __construct($em, $um)
    {
        $this->em = $em;
        $this->user_manager = $um;

        $this->calendar_class = '';
        $this->calendarobjects_class = '';
    }

    /**
     * Returns a list of calendars for a principal.
     *
     * Every project is an array with the following keys:
     *  * id, a unique id that will be used by other functions to modify the
     *    calendar. This can be the same as the uri or a database key.
     *  * uri, which the basename of the uri with which the calendar is
     *    accessed.
     *  * principaluri. The owner of the calendar. Almost always the same as
     *    principalUri passed to this method.
     *
     * Furthermore it can contain webdav properties in clark notation. A very
     * common one is '{DAV:}displayname'.
     *
     * @param string $principalUri
     * @return array
     */
    public function getCalendarsForUser($principalUri)
    {
	    $calendars = [];

        $username = substr(strrchr($principalUri, '/'),1);
        $user = $this->user_manager->findUserByUsername($username);
        if($user instanceof User === false) {
            return $calendars;
        }
        $usercalendar = $user->getCalendar();

        if($usercalendar instanceof Calendar) {
        $ctag = ($usercalendar->getUpdatedAt() instanceof \DateTime !== false)?$usercalendar->getUpdatedAt()->getTimestamp():time();
	    $components = ['VEVENT'];
            $calendar = [
                'id' => $usercalendar->getId(),
                'uri' => 'doctorio',
                'principaluri' => $principalUri,
                '{' . CalDAV\Plugin::NS_CALENDARSERVER . '}getctag' => 'http://sabre.io/ns/sync/'.$ctag,
                '{http://sabredav.org/ns}sync-token' => $ctag,
                '{' . CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new CalDAV\Xml\Property\SupportedCalendarComponentSet($components),
                '{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp' => new CalDAV\Xml\Property\ScheduleCalendarTransp('opaque'),
            ];
            $calendars[] = $calendar;
        }

        return $calendars;
    }

    /**
     * Creates a new calendar for a principal.
     *
     * If the creation was a success, an id must be returned that can be used to reference
     * this calendar in other methods, such as updateCalendar.
     *
     * @param string $principalUri
     * @param string $calendarUri
     * @param array $properties
     * @return void
     */
    public function createCalendar($principalUri, $calendarUri, array $properties)
    {
        return;
    }

    /**
     * Updates properties for a calendar.
     *
     * The mutations array uses the propertyName in clark-notation as key,
     * and the array value for the property value. In the case a property
     * should be deleted, the property value will be null.
     *
     * This method must be atomic. If one property cannot be changed, the
     * entire operation must fail.
     *
     * If the operation was successful, true can be returned.
     * If the operation failed, false can be returned.
     *
     * Deletion of a non-existent property is always successful.
     *
     * Lastly, it is optional to return detailed information about any
     * failures. In this case an array should be returned with the following
     * structure:
     *
     * array(
     *   403 => array(
     *      '{DAV:}displayname' => null,
     *   ),
     *   424 => array(
     *      '{DAV:}owner' => null,
     *   )
     * )
     *
     * In this example it was forbidden to update {DAV:}displayname.
     * (403 Forbidden), which in turn also caused {DAV:}owner to fail
     * (424 Failed Dependency) because the request needs to be atomic.
     *
     * @param mixed $calendarId
     * @param $calendarId
     * @param \Sabre\DAV\PropPatch $propPatch
     * @return bool|array
     */
    public function updateCalendar($calendarId, \Sabre\DAV\PropPatch $propPatch)
    {
        $supportedProperties = array_keys($this->propertyMap);
        $supportedProperties[] = '{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp';

        $propPatch->handle($supportedProperties, function($mutations) use ($calendarId) {
            $newValues = [];
            foreach ($mutations as $propertyName => $propertyValue) {

                switch ($propertyName) {
                    case '{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp' :
                        $fieldName = 'transparent';
                        $newValues[$fieldName] = $propertyValue->getValue() === 'transparent';
                        break;
                    default :
                        $fieldName = $this->propertyMap[$propertyName];
                        $newValues[$fieldName] = $propertyValue;
                        break;
                }

            }
            $valuesSql = [];
            foreach ($newValues as $fieldName => $value) {
                $valuesSql[] = $fieldName . ' = ?';
            }

            //update calendar in database
            //...
            
            return true;

        });
    }

    /**
     * Delete a calendar and all it's objects
     *
     * @param mixed $calendarId
     * @return void
     */
    public function deleteCalendar($calendarId)
    {
        return;
    }

    /**
     * Returns all calendar objects within a calendar.
     *
     * Every item contains an array with the following keys:
     *   * id - unique identifier which will be used for subsequent updates
     *   * calendardata - The iCalendar-compatible calendar data
     *   * uri - a unique key which will be used to construct the uri. This can be any arbitrary string.
     *   * lastmodified - a timestamp of the last modification time
     *   * etag - An arbitrary string, surrounded by double-quotes. (e.g.:
     *   '  "abcdef"')
     *   * calendarid - The calendarid as it was passed to this function.
     *   * size - The size of the calendar objects, in bytes.
     *
     * Note that the etag is optional, but it's highly encouraged to return for
     * speed reasons.
     *
     * The calendardata is also optional. If it's not returned
     * 'getCalendarObject' will be called later, which *is* expected to return
     * calendardata.
     *
     * If neither etag or size are specified, the calendardata will be
     * used/fetched to determine these numbers. If both are specified the
     * amount of times this is needed is reduced by a great degree.
     *
     * @param mixed $calendarId
     * @return array
     */
    public function getCalendarObjects($calendarId)
    {
        $result = [];

        $usercalendar = $this->em->getRepository('ArzttermineCalendarBundle:Calendar')->find($calendarId);
        if($usercalendar instanceof Calendar) {
            /* @var Event $event */
            foreach ($usercalendar->getEvents() as $event) {
                $result[] = [
                    'id'           => $event->getId(),
                    'uri'          => $event->getObjectUri(),
                    'lastmodified' => $event->getUpdatedAt(),
                    'etag'         => '"' . $event->getEtag() . '"',
                    'calendarid'   => $calendarId,
                    'size'         => (int)$event->getSize(),
                    'calendardata'  => $event->getCaldavData(),
                    'component'    => 'vevent',
                ];
            }
        }

        return $result;
    }

    /**
     * Returns information from a single calendar object, based on it's object
     * uri.
     *
     * The returned array must have the same keys as getCalendarObjects. The
     * 'calendardata' object is required here though, while it's not required
     * for getCalendarObjects.
     *
     * This method must return null if the object did not exist.
     *
     * @param mixed $calendarId
     * @param string $objectUri
     * @return array|null
     */
    public function getCalendarObject($calendarId, $objectUri)
    {
        //get from database via $calendarId and $objectUri
        $event = $this->em->getRepository('ArzttermineCalendarBundle:Event')->findOneBy(array('calendar'=>$calendarId, 'objectUri'=>$objectUri));

        if($event === null) {
            return null;
        }

        return [
                'id'           => $event->getId(),
                'uri'          => $event->getObjectUri(),
                'lastmodified' => $event->getUpdatedAt(),
                'etag'         => '"' . $event->getEtag() . '"',
                'calendarid'   => $calendarId,
                'size'         => (int)$event->getSize(),
                'calendardata'  => $event->getCaldavData(),
                'component'    => 'vevent',
            ];
    }

    /**
     * Returns a list of calendar objects.
     *
     * This method should work identical to getCalendarObject, but instead
     * return all the calendar objects in the list as an array.
     *
     * If the backend supports this, it may allow for some speed-ups.
     *
     * @param mixed $calendarId
     * @param array $uris
     * @return array
     */
    function getMultipleCalendarObjects($calendarId, array $uris)
    {
        $result = [];
        $events = $this->em->getRepository('ArzttermineCalendarBundle:Event')->findBy(array('calendar'=>$calendarId, 'objectUri'=>$uris));

        foreach($events as $event)
        {
            $result[] = [
                'id'           => $event->getId(),
                'uri'          => $event->getObjectUri(),
                'lastmodified' => $event->getUpdatedAt(),
                'etag'         => '"' . $event->getEtag() . '"',
                'calendarid'   => $calendarId,
                'size'         => (int)$event->getSize(),
                'calendardata' => $event->getCaldavData(),
                'component'    => 'vevent',
            ];
        }


        return $result;
    }

    /**
     * Creates a new calendar object.
     *
     * It is possible return an etag from this function, which will be used in
     * the response to this PUT request. Note that the ETag must be surrounded
     * by double-quotes.
     *
     * However, you should only really return this ETag if you don't mangle the
     * calendar-data. If the result of a subsequent GET to this object is not
     * the exact same as this request body, you should omit the ETag.
     *
     * @param mixed $calendarId
     * @param string $objectUri
     * @param string $calendarData
     * @return string|null
     */
    public function createCalendarObject($calendarId, $objectUri, $calendarData)
    {
        $extraData = $this->getDenormalizedData($calendarData);

        if($extraData['componentType'] != 'VEVENT') {
            return null;
        }

        $calendar = $this->em->getRepository('ArzttermineCalendarBundle:Calendar')->find($calendarId);


        //store in database
        /* @var Event $event */
        $event = new Event();
        $event->setEtag($extraData['etag']);
        $event->setSize($extraData['size']);
        $event->setUid($extraData['uid']);
        $event->setObjectUri($objectUri);
        $event->setCaldavData($calendarData);
        $event->setTitle($extraData['title']);
        $event->setComment($extraData['comment']);
        $startDate = new \DateTime();
        $startDate->setTimestamp($extraData['firstOccurence']);
        $event->setStartDate($startDate);
        $endDate = new \DateTime();
        $endDate->setTimestamp($extraData['lastOccurence']);
        $event->setEndDate($endDate);
        $days = $startDate->diff($endDate)->format('%R%a');
        $event->setAllDay(($days == '+1'));
        //allDay events have endDate == startDate on doctorio calendar
        if($event->getAllDay() === true) {
            $endDate->setTimestamp($extraData['firstOccurence']);
        }

        $event->setCalendar($calendar);
        $calendar->addEvent($event);
        $event->setResources($calendar->getDefaultResources());
        $event->setRepeat(false);

        $this->em->persist($event);
        $this->em->flush();


        return '"' . $extraData['etag'] . '"';
    }

    /**
     * Parses some information from calendar objects, used for optimized
     * calendar-queries.
     *
     * Returns an array with the following keys:
     *   * etag - An md5 checksum of the object without the quotes.
     *   * size - Size of the object in bytes
     *   * componentType - VEVENT, VTODO or VJOURNAL
     *   * firstOccurence
     *   * lastOccurence
     *   * uid - value of the UID property
     *
     * @param string $calendarData
     * @return array
     */
    protected function getDenormalizedData($calendarData) {

        $vObject = \Sabre\VObject\Reader::read($calendarData);
        $componentType = null;
        $component = null;
        $firstOccurence = null;
        $lastOccurence = null;
        $uid = null;
        foreach ($vObject->getComponents() as $component) {
            if ($component->name !== 'VTIMEZONE') {
                $componentType = $component->name;
                $uid = (string)$component->UID;
                break;
            }
        }
        if (!$componentType) {
            throw new \Sabre\DAV\Exception\BadRequest('Calendar objects must have a VJOURNAL, VEVENT or VTODO component');
        }
        if ($componentType === 'VEVENT') {
            $firstOccurence = $component->DTSTART->getDateTime()->getTimeStamp();
            // Finding the last occurence is a bit harder
            if (!isset($component->RRULE)) {
                if (isset($component->DTEND)) {
                    $lastOccurence = $component->DTEND->getDateTime()->getTimeStamp();
                } elseif (isset($component->DURATION)) {
                    $endDate = clone $component->DTSTART->getDateTime();
                    $endDate = $endDate->add(\Sabre\VObject\DateTimeParser::parse($component->DURATION->getValue()));
                    $lastOccurence = $endDate->getTimeStamp();
                } elseif (!$component->DTSTART->hasTime()) {
                    $endDate = clone $component->DTSTART->getDateTime();
                    $endDate = $endDate->modify('+1 day');
                    $lastOccurence = $endDate->getTimeStamp();
                } else {
                    $lastOccurence = $firstOccurence;
                }

                $title = (isset($component->SUMMARY))?$component->SUMMARY->getRawMimeDirValue():'';
                $comment = (isset($component->DESCRIPTION))?$component->DESCRIPTION->getRawMimeDirValue():'';
            } else {
                $it = new \Sabre\VObject\Recur\EventIterator($vObject, (string)$component->UID);
                $maxDate = new \DateTime(self::MAX_DATE);
                if ($it->isInfinite()) {
                    $lastOccurence = $maxDate->getTimeStamp();
                } else {
                    $end = $it->getDtEnd();
                    while ($it->valid() && $end < $maxDate) {
                        $end = $it->getDtEnd();
                        $it->next();

                    }
                    $lastOccurence = $end->getTimeStamp();
                }

            }
        }

        // Destroy circular references to PHP will GC the object.
        $vObject->destroy();

        return [
            'etag'           => md5($calendarData),
            'size'           => strlen($calendarData),
            'componentType'  => $componentType,
            'firstOccurence' => $firstOccurence,
            'lastOccurence'  => $lastOccurence,
            'uid'            => $uid,
            'title'          => $title,
            'comment'        => $comment
        ];

    }

    /**
     * Updates an existing calendarobject, based on it's uri.
     *
     * It is possible return an etag from this function, which will be used in
     * the response to this PUT request. Note that the ETag must be surrounded
     * by double-quotes.
     *
     * However, you should only really return this ETag if you don't mangle the
     * calendar-data. If the result of a subsequent GET to this object is not
     * the exact same as this request body, you should omit the ETag.
     *
     * @param mixed $calendarId
     * @param string $objectUri
     * @param string $calendarData
     * @return string|null
     */
    public function updateCalendarObject($calendarId, $objectUri, $calendarData)
    {
        $extraData = $this->getDenormalizedData($calendarData);

        if($extraData['componentType'] != 'VEVENT') {
            return null;
        }

        $event = $this->em->getRepository('ArzttermineCalendarBundle:Event')->findOneBy(array('calendar'=>$calendarId, 'objectUri'=>$objectUri));

        if($event === null) {
            return null;
        }

        //store in database
        /* @var Event $event */
        $event->setEtag($extraData['etag']);
        $event->setSize($extraData['size']);
        $event->setUid($extraData['uid']);
        $event->setCaldavData($calendarData);
        $event->setTitle($extraData['title']);
        $event->setComment($extraData['comment']);
        $startDate = new \DateTime();
        $startDate->setTimestamp($extraData['firstOccurence']);
        $event->setStartDate($startDate);
        $endDate = new \DateTime();
        $endDate->setTimestamp($extraData['lastOccurence']);
        $event->setEndDate($endDate);
        $days = $startDate->diff($endDate)->format('%R%a');
        $event->setAllDay(($days == '+1'));
        //allDay events have endDate == startDate on doctorio calendar
        if($event->getAllDay() === true) {
            $endDate->setTimestamp($extraData['firstOccurence']);
        }
        $event->getCalendar()->setUpdatedAt(new \DateTime());

        $this->em->persist($event);
        $this->em->flush();


        return '"' . $extraData['etag'] . '"';
    }

    /**
     * Deletes an existing calendar object.
     *
     * @param mixed $calendarId
     * @param string $objectUri
     * @return void
     */
    public function deleteCalendarObject($calendarId, $objectUri)
    {
        //simply delete from database
        $event = $this->em->getRepository('ArzttermineCalendarBundle:Event')->findOneBy(array('calendar'=>$calendarId, 'objectUri'=>$objectUri));

        if($event !== null) {
            $this->em->remove($event);
            $this->em->flush();
        }
    }

    /**
     * Performs a calendar-query on the contents of this calendar.
     *
     * The calendar-query is defined in RFC4791 : CalDAV. Using the
     * calendar-query it is possible for a client to request a specific set of
     * object, based on contents of iCalendar properties, date-ranges and
     * iCalendar component types (VTODO, VEVENT).
     *
     * This method should just return a list of (relative) urls that match this
     * query.
     *
     * The list of filters are specified as an array. The exact array is
     * documented by Sabre\CalDAV\CalendarQueryParser.
     *
     * Note that it is extremely likely that getCalendarObject for every path
     * returned from this method will be called almost immediately after. You
     * may want to anticipate this to speed up these requests.
     *
     * This method provides a default implementation, which parses *all* the
     * iCalendar objects in the specified calendar.
     *
     * This default may well be good enough for personal use, and calendars
     * that aren't very large. But if you anticipate high usage, big calendars
     * or high loads, you are strongly adviced to optimize certain paths.
     *
     * The best way to do so is override this method and to optimize
     * specifically for 'common filters'.
     *
     * Requests that are extremely common are:
     *   * requests for just VEVENTS
     *   * requests for just VTODO
     *   * requests with a time-range-filter on either VEVENT or VTODO.
     *
     * ..and combinations of these requests. It may not be worth it to try to
     * handle every possible situation and just rely on the (relatively
     * easy to use) CalendarQueryValidator to handle the rest.
     *
     * Note that especially time-range-filters may be difficult to parse. A
     * time-range filter specified on a VEVENT must for instance also handle
     * recurrence rules correctly.
     * A good example of how to interprete all these filters can also simply
     * be found in Sabre\CalDAV\CalendarQueryFilter. This class is as correct
     * as possible, so it gives you a good idea on what type of stuff you need
     * to think of.
     *
     * @param mixed $calendarId
     * @param array $filters
     * @return array
     */
    public function calendarQuery($calendarId, array $filters)
    {
        $result = [];

        if (count($filters['comp-filters']) > 0 && !$filters['comp-filters'][0]['is-not-defined']) {
            if (isset($filters['comp-filters'][0]['time-range'])) {
                $timeRange = $filters['comp-filters'][0]['time-range'];
            }
        }

        $qb = $this->em->getRepository('ArzttermineCalendarBundle:Event')->createQueryBuilder('u');
        $qb->select('partial u.{id, objectUri}')
            ->where('u.calendar = :calendar')
            ->setParameter('calendar', $calendarId);
        $qb->andWhere($qb->expr()->isNotNull('u.objectUri'));

        if(isset($timeRange['start'])) {
            $qb->andWhere($qb->expr()->gte('u.start_date', ':start_date'))
                ->setParameter('start_date', $timeRange['start']->format('Y-m-d H:i:s'));
        }
        if(isset($timeRange['end'])) {
            $qb->andWhere($qb->expr()->lte('u.end_date', ':end_date'))
                ->setParameter('end_date', $timeRange['end']->format('Y-m-d H:i:s'));
        }

        $items = $qb->getQuery()->getScalarResult();

        foreach($items as $event) {
            $result[] = $event['u_objectUri'];
        }

        return $result;
    }

    /**
     * Searches through all of a users calendars and calendar objects to find
     * an object with a specific UID.
     *
     * This method should return the path to this object, relative to the
     * calendar home, so this path usually only contains two parts:
     *
     * calendarpath/objectpath.ics
     *
     * If the uid is not found, return null.
     *
     * This method should only consider * objects that the principal owns, so
     * any calendars owned by other principals that also appear in this
     * collection should be ignored.
     *
     * @param string $principalUri
     * @param string $uid
     * @return string|null
     */
    function getCalendarObjectByUID($principalUri, $uid)
    {
        //get from database via $calendarId and $objectUri
        $event = $this->em->getRepository('ArzttermineCalendarBundle:Event')->findOneBy(array('uid'=>$uid));

        if($event === null) {
            return null;
        }

        return [
            'id'           => $event->getId(),
            'uri'          => $event->getObjectUri(),
            'lastmodified' => $event->getUpdatedAt(),
            'etag'         => '"' . $event->getEtag() . '"',
            'calendarid'   => $calendarId,
            'size'         => (int)$event->getSize(),
            'calendardata'  => $event->getCaldavData(),
            'component'    => 'vevent',
        ];
    }
}

