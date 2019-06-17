<?php

namespace SampleJunction\LaravelLoggerForTeams;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

class LoggerHandler extends AbstractProcessingHandler
{
    /** @var string */
    private $url;

    /** @var string */
    private $style;

    /** @var string */
    private $name;

    /**
     * @param $url
     * @param int $level
     * @param string $name
     * @param bool $bubble
     */
    public function __construct($url, $level = Logger::DEBUG, $style, $name, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->url   = $url;
        $this->style = $style;
        $this->name  = $name;
    }

    /**
     * @param array $record
     *
     * @return LoggerMessage
     */
    protected function getMessage(array $record)
    {
        if ($this->style == 'card') {
            // Include context as facts to send to microsoft teams
            // Added Sent Date Info
            $facts = array_merge($record['context'], [[
                'name'  => 'Sent Date',
                'value' => date('D, M d Y H:i:s e'),
            ]]);
			
			if(!empty($record['context'])){
                if( array_keys($facts) !== range(0, count($facts) - 1) ){
                    $newFacts = [];
                    $contexts_names = array_keys($facts);
                    foreach ($contexts_names as $index) {

                        if (is_a($facts[$index], 'Exception')) {
                            $message = [
                                'message' => $facts[$index]->getMessage(),
                                'code' => $facts[$index]->getCode(),
                                'file' => $facts[$index]->getFile(),
                                'line' => $facts[$index]->getLine(),
                                'trace' => $facts[$index]->getTraceAsString(),
                            ];

                            $newFacts[] = [
                                'name' => $index,
                                'value' => json_encode($message, JSON_PRETTY_PRINT),
                            ];
                        }else{
                            $newFacts[] = [
                                'name' => $facts[$index]['name'],
                                'value' => $facts[$index]['value'],
                            ];
                        }
                    }
                    $facts = $newFacts;
                }
            }

            return $this->useCardStyling($record['level_name'], $record['message'], $facts);
        } else {
            return $this->useSimpleStyling($record['level_name'], $record['message']);
        }
    }

    /**
     * Styling message as simple message
     *
     * @param String $name
     * @param String $message
     * @param array  $facts
     */
    public function useCardStyling($name, $message, $facts)
    {
        $loggerColour = new LoggerColour($name);

        return new LoggerMessage([
            'summary'    => $name . ($this->name ? ': ' . $this->name : ''),
            'themeColor' => (string) $loggerColour,
            'sections'   => [
                [
                    'activityTitle'    => $this->name,
                    'activitySubtitle' => '<span style="color:#' . (string) $loggerColour . '">' . $name . '</span>',
                    'activityText'     => $message,
                    'activityImage'    => (string) new LoggerAvatar($name, $loggerColour),
                    'facts'            => $facts,
                    'markdown'         => true
                ]
            ]
        ]);
    }

    /**
     * Styling message as simple message
     *
     * @param String $name
     * @param String $message
     */
    public function useSimpleStyling($name, $message)
    {
        $loggerColour = new LoggerColour($name);

        return new LoggerMessage([
            'text'       => ($this->name ? $this->name . ' - ' : '') . '<span style="color:#' . (string) $loggerColour . '">' . $name . '</span>: ' . $message,
            'themeColor' => (string) $loggerColour,
        ]);
    }

    /**
     * @param array $record
     */
    protected function write(array $record)
    {
        $json = json_encode($this->getMessage($record));

        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json)
        ]);

        curl_exec($ch);
    }
}
