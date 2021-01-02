<?php

/**
 * @property string $title
 * @property \DateTime $from
 * @property \DateTime $to
 * @property string $description
 * @property string $address
 */
class ga_add_to_calendar
{
    /** @var string */
    protected $title;

    /** @var \DateTime */
    protected $from;

    /** @var \DateTime */
    protected $to;

    /** @var string */
    protected $description;

    /** @var string */
    protected $address;

    public function __construct($title, DateTime $from, DateTime $to)
    {
        $this->title = $title;
        $this->from = $from;
        $this->to = $to;
    }

    /**
     * @param string $title
     * @param \DateTime $from
     * @param \DateTime $to
     *
     * @return static
     */
    public static function create($title, DateTime $from, DateTime $to)
    {
        return new static($title, $from, $to);
    }

    /**
     * @param string $description
     *
     * @return $this
     */
    public function description($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param string $address
     *
     * @return $this
     */
    public function address($address)
    {
        $this->address = $address;

        return $this;
    }

    public function google()
    {
        $format = 'Ymd\THis';

        $url = 'https://calendar.google.com/calendar/render?action=TEMPLATE';

        $url .= '&text=' . urlencode($this->title);
        $url .= '&dates=' . $this->from->format($format) . '/' . $this->to->format($format);

        if ($this->description) {
            $url .= '&details=' . urlencode($this->description);
        }

        if ($this->address) {
            $url .= '&location=' . urlencode($this->address);
        }

        $url .= '&sprop=&sprop=name:';

        return $url;
    }

    // public function ics( $appID = 0 ) {

    //         // Date format
    //         $tmpDateFormat = 'Ymd\THis';

    //         if( !$appID ) :

    //                 $url  = 'BEGIN:VCALENDAR' . PHP_EOL;
    //                 $url .= 'VERSION:2.0'     . PHP_EOL;
    //                 $url .= 'BEGIN:VEVENT'    . PHP_EOL;
    //                 $url .= 'DTSTART;TZID:' . $this ->from->format( $tmpDateFormat ) . PHP_EOL;
    //                 $url .= 'DTEND;TZID:'   . $this -> to -> format( $tmpDateFormat )   . PHP_EOL;
    //                 $url .= 'SUMMARY:' . addslashes( $this -> title ) . PHP_EOL;

    //                 if( $this -> description )
    //                     $url .= 'DESCRIPTION:' . addslashes( $this -> description ) . PHP_EOL;

    //                 if( $this -> address )
    //                     $url .= 'LOCATION:' . addslashes( str_replace( ',', '', $this -> address ) ) . PHP_EOL;

    //                 $url .= 'END:VEVENT' . PHP_EOL;
    //                 $url .= 'END:VCALENDAR';

    //                 return $url;

    //         endif;

    //         // Generate the array
    //         $tmpData = [
    //                 'title'       => $this -> title,
    //                 'from'        => $this -> from -> format( $tmpDateFormat ),
    //                 'to'          => $this -> to -> format( $tmpDateFormat ),
    //                 'description' => $this -> description,
    //                 'address'     => $this -> address
    //         ];

    //         // Generate a meta key which value is array with ics data
    //         update_post_meta( $appID, 'calendarData', $tmpData );

    //         // Return the link to .ics file
    //         return get_site_url( ) . '/?ap-ics=' . $appID;
    // }

    public function ics($appID = 0)
    {

        $tmpDateFormat = 'Ymd\THis';
        $url = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'BEGIN:VEVENT',
            'SUMMARY:' . $this->title,
        ];

        $url[] = 'DTSTART;TZID=' . $this->from->format($tmpDateFormat);
        $url[] = 'DTEND;TZID=' . $this->to->format($tmpDateFormat);
        if ($this->description) {
            $url[] = 'DESCRIPTION:' . $this->escapeString($this->description);
        }
        if ($this->address) {
            $url[] = 'LOCATION:' . $this->escapeString($this->address);
        }
        $url[] = 'END:VEVENT';
        $url[] = 'END:VCALENDAR';
        $redirectLink = implode('%0d%0a', $url);
        return 'data:text/calendar;charset=utf8,' . $redirectLink;
    }

    public static function generate_ics_file($appID)
    {
        // Get meta
        $tmpData = get_post_meta($appID, 'calendarData', true);
        if (empty($tmpData))
            return;

        header('Content-Type: text/Calendar; charset=utf-8');
        header('Content-Disposition: inline; filename=mohawk-event.ics');

        $url  = 'BEGIN:VCALENDAR' . PHP_EOL;
        $url .= 'VERSION:2.0'     . PHP_EOL;
        $url .= 'BEGIN:VEVENT'    . PHP_EOL;
        $url .= 'DTSTART:' . $tmpData["from"] . PHP_EOL;
        $url .= 'DTEND:'   . $tmpData["to"]   . PHP_EOL;
        $url .= 'SUMMARY:' . addslashes($tmpData['title']) . PHP_EOL;

        if ($tmpData['description'])
            $url .= 'DESCRIPTION:' . addslashes($tmpData['description']) . PHP_EOL;

        if ($tmpData['address'])
            $url .= 'LOCATION:' . addslashes(str_replace(',', '', $tmpData["address"])) . PHP_EOL;

        $url .= 'END:VEVENT' . PHP_EOL;
        $url .= 'END:VCALENDAR';

        die($url);
    }

    public function yahoo()
    {
        $format = 'Ymd\THis';
        $url = 'https://calendar.yahoo.com/?v=60&view=d&type=20';

        $url .= '&title=' . urlencode($this->title);
        $url .= '&st=' . $this->from->format($format);
        $url .= '&et=' . $this->to->format($format);
        $url .= '&dur=23:59';
        if ($this->description) {
            $url .= '&desc=' . urlencode($this->description);
        }

        if ($this->address) {
            $url .= '&in_loc=' . urlencode($this->address);
        }

        return $url;
    }

    public function outlook()
    {
        $format = 'Ymd\THis';
        $url = 'https://outlook.live.com/owa/?path=/calendar/action/compose&rru=addevent';
        $url .= '&startdt=' . $this->from->format($format);
        $url .= '&enddt=' . $this->to->format($format);
        $url .= '&subject=' . urlencode($this->title);
        if ($this->description) {
            $url .= '&body=' . urlencode($this->description);
        }
        if ($this->address) {
            $url .= '&location=' . urlencode($this->address);
        }
        return $url;
    }

    /** @see https://tools.ietf.org/html/rfc5545.html#section-3.3.11 */
    protected function escapeString(string $field)
    {
        return addcslashes($field, "\r\n,;");
    }
}



//$from = DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 09:00');
//$to   = DateTime::createFromFormat('Y-m-d H:i', '2018-02-01 18:00');

//$link = ga_add_to_calendar::create('Sebastian\'s birthday', $from, $to)->description('Cookies & cocktails!')->address('Samberstraat 69D, 2060 Antwerpen');

// Generate a link to create an event on Google calendar
//echo $link->google();

// Generate a link to create an event on Yahoo calendar
//echo $link->yahoo();

// Generate a data uri for an ics file (for iCal & Outlook)
//echo $link->ics();

//$ics = $link->ics();

//echo '<a href="'.$ics.'">Apple Calendar</a>';
