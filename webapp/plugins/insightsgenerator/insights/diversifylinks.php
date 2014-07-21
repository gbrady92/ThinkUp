 <?php
 /*
  Plugin Name: Diversify your links
  Description: Encourages user to share links from different sources.
  When: 25th of the month, Every Wednesday.
  */
 /**
  *
  * ThinkUp/webapp/plugins/insightsgenerator/insights/diversifyyourlinks.php
  *
  * Copyright (c) 2014 Gareth Brady
  *
  * LICENSE:
  *
  * This file is part of ThinkUp (http://thinkup.com).
  *
  * ThinkUp is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
  * License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any
  * later version.
  *
  * ThinkUp is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
  * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
  * details.
  *
  * You should have received a copy of the GNU General Public License along with ThinkUp.  If not, see
  * <http://www.gnu.org/licenses/>.
  *
  * @license http://www.gnu.org/licenses/gpl.html
  * @copyright 2014 Gareth Brady
  * @author Gareth Brady <gareth.brady92 [at] gmail [dot] com>
  */

class DiversifyLinksInsight extends InsightPluginParent implements InsightPlugin {
    /**
     * Slug for this insight
     */
    var $slug = ''; 
    public function generateInsight(Instance $instance, User $user, $last_week_of_posts, $number_days) {
        parent::generateInsight($instance, $user, $last_week_of_posts, $number_days);
        $this->logger->logInfo("Begin generating insight", __METHOD__.','.__LINE__);

        // $should_generate_insight_weekly = $this->shouldGenerateWeeklyInsight($this->slug, $instance, 'today',
        // $regenerate=false, 3);
        // $should_generate_insight_monthly = $this->shouldGenerateMonthlyInsight($this->slug, $instance, 'today',
        // $regenerate=false, 25);
        // $should_generate_insight_weekly = true;
        $should_generate_insight_monthly = true;


        if($should_generate_insight_weekly) {
            $link_dao = DAOFactory::getDAO('LinkDAO');
            $links = $link_dao->getLinksByUserSinceDaysAgo($instance->network_user_id, $instance->network, 0, 7);
            $slug = "diversify_links_weekly";
            $time_frame = "week";
            if(count($links) > 5) {
               $this->getInsightData($links,$slug,$time_frame,$instance,$link_dao);
            }
        } if($should_generate_insight_monthly) {
            $link_dao = DAOFactory::getDAO('LinkDAO');
            $links = $link_dao->getLinksByUserSinceDaysAgo($instance->network_user_id, $instance->network, 0, date('t'));
            $slug = "diversify_links_monthly";
            $time_frame = "month";
            if(count($links > 5)) {
                $this->getInsightData($links,$slug,$time_frame,$instance,$link_dao);
            }
        }
        $this->logger->logInfo("Done generating insight", __METHOD__.','.__LINE__);
    }
    /**
     * Get the Url data. Calculates the data for the most popular link and
     * if it accounts for over 50% of all links shared.
     * Creats vis_data for pie_chart.
     *
     * @param arr array of links.
     * @param str string declaring what data user wants returned.
     * @return str The url of the site or NULL.
     * @return json Contains data to be passed to GoogleCharts.
     */
    private function getUrlData($links, $get_option) {
        $domain;
        $url_counts = array();

        foreach($links as $link) {
            if($link->expanded_url == "") {
                continue;
            } else {
                $url = parse_url($link->expanded_url);
                $domain = $url['host'];
            }
            if(array_key_exists($domain, $url_counts)) {
                $url_counts[$domain]++;
            } else {
                $url_counts[$domain] = 1;
            }
        }
        if($get_option == 'popular_url') {
            return array_search(max($url_counts),$url_counts);
        }

        if($get_option == 'most_used_url') {
            if(max($url_counts)/array_sum($url_counts) > 0.5) {
                return array_search(max($url_counts),$url_counts);
            } else {
                return null;
            }
        } elseif($get_option =='vis_data') {
            $resultset = array();
            $metadata = array();
            foreach ($url_counts as $links => $count) {
                $resultset[] = array('c' => array( array('v' =>$links), array('v' => $count)));
                $metadata = array( array('type' => 'string', 'label' => 'Url'),
                            array('type' => 'number', 'label' => 'Number of Shares'),
                            );
            }
            $vis_data = json_encode(array('rows' => $resultset, 'cols' => $metadata));
            return $vis_data;
        }
    }

    private function getInsightData($links, $slug, $time_frame,$instance,$link_dao) {
        $insight = new Insight();
        $terms = new InsightTerms($instance->network);
        $most_used_url = $this->getUrlData($links, 'most_used_url');
        if($most_used_url == NULL) {
            $popular_url = $this->getUrlData($links, 'popular_url');
            $vis_data = $this->getUrlData($links,'vis_data');
            $insight->slug = $slug;
            $insight->instance_id = $instance->id;
            $insight->date = $this->insight_date;
            $insight->text = $this->getVariableCopy(array(
              "Looks like %username's most shared site was $popular_url",
              "%username must like $popular_url because it's last $time_frame's most shared site.",
              "Looks like $popular_url was last $time_frame's most shared site."
              ));
            $insight->headline = $this->getVariableCopy(array(
                    "What links has %username been sharing over the last $time_frame ?",
                    "Here's a breakdown of the links %username shared last $time_frame.",
                    "Lets see what links %username liked to share last $time_frame."
                ), array('network' => ucfirst($instance->network)));
          $insight->setBarChart($vis_data);
          $insight->filename = basename(__FILE__, ".php");
          $this->insight_dao->insertInsight($insight);
        }

        if($most_used_url != NULL) {
            $graph_links = $link_dao->getLinksByUserSinceDaysAgo($instance->network_user_id,
            $instance->network, 100, 0); //Gets link objects for use in the graph.
            $last_x_links_text ='';
            $followers_friends_text = $terms->getNoun('follower', InsightTerms::PLURAL);

            if(count($graph_links) >= 50 && count($graph_links) < 100 ) {
                $fifty_links = array_slice($graph_links, 0, 50, true);
                $vis_data = $this->getUrlData($fifty_links,'vis_data');
                $insight->setBarChart($vis_data);
            } elseif(count($graph_links) == 100) {
                $vis_data = $this->getUrlData($graph_links,'vis_data');
                $insight->setBarChart($vis_data);
            }
            $text1 = "Over <strong>half</strong> of the links $this->username shared ";
            $text1 .= "last $time_frame came from <strong>$most_used_url</strong>.<br> ";
            $text1 .= "";
            $text1 .= "$this->username. <br><br> $last_x_links_text";
            $text2 = "More than <strong>50%</strong> of the links $this->username shared last $time_frame went to ";
            $text2 .= "<strong>$most_used_url</strong>.<br>";
            $text3 = "Over <strong>50%</strong> of the links $this->username shared last $time_frame went to ";
            $text3 .= "<strong>$most_used_url</strong>.<br> ";
            $insight->slug = $slug;
            $insight->instance_id = $instance->id;
            $insight->date = $this->insight_date;
            $insight->headline = $this->getVariableCopy(array(
                "What link was %username's clear favorite last $time_frame ?",
                "Looks like $instance->network_username likes $most_used_url.",
                "Spread the love."
            ), array('network' => ucfirst($instance->network)));
            $insight->text = $this->getVariableCopy(array(
                $text1,$text2,$text3
            ));
            $insight->filename = basename(__FILE__, ".php");
            $this->insight_dao->insertInsight($insight);
        }
    }
}

 $insights_plugin_registrar = PluginRegistrarInsights::getInstance();
 $insights_plugin_registrar->registerInsightPlugin('DiversifyLinksInsight');