<?php
/*!
* HybridAuth
* http://hybridauth.sourceforge.net | http://github.com/hybridauth/hybridauth
* (c) 2009-2012, HybridAuth authors | http://hybridauth.sourceforge.net/licenses.html 
*/

/**
* Hybrid_Providers_Usosweb provider adapter based on OAuth1 protocol
* Adapter to Usosweb API by Henryk Michalewski
*/

class Hybrid_Providers_Usosweb extends Hybrid_Provider_Model_OAuth1
{
	/**
	* idp wrappers initializer 
	*/
	/* Required scopes. The only functionality of this application is to say hello,
    * so it does not really require any. But, if you want, you may access user's
    * email, just do the following:
    * - put array('email') here,
    * - append 'email' to the 'fields' argument of 'services/users/user' method,
    *   you will find it below in this script.
    */
	
	function initialize()
	{
		parent::initialize();
		
        $scopes = array("studies", "grades");

		// Provider api end-points 
		$this->api->api_base_url      = "https://usosapps.uw.edu.pl/";
		$this->api->request_token_url = "https://usosapps.uw.edu.pl/services/oauth/request_token?scopes=".implode("|", $scopes);
		$this->api->access_token_url  = "https://usosapps.uw.edu.pl/services/oauth/access_token";
		$this->api->authorize_url = "https://usosapps.uw.edu.pl/services/oauth/authorize";

	}
	
	
        /**
	* begin login step 
	*/
	function loginBegin()
	{
		$tokens = $this->api->requestToken( $this->endpoint ); 

		// request tokens as received from provider
		$this->request_tokens_raw = $tokens;
		
		// check the last HTTP status code returned
		if ( $this->api->http_code != 200 ){
			throw new Exception( "Authentication failed! {$this->providerId} returned an error. " . $this->errorMessageByStatus( $this->api->http_code ), 5 );
		}

		if ( ! isset( $tokens["oauth_token"] ) ){
			throw new Exception( "Authentication failed! {$this->providerId} returned an invalid oauth_token.", 5 );
		}

		$this->token( "request_token"       , $tokens["oauth_token"] ); 
		$this->token( "request_token_secret", $tokens["oauth_token_secret"] ); 

		# redirect the user to the provider authentication url
		Hybrid_Auth::redirect( $this->api->authorizeUrl( $tokens ) );
	}
		

	/**
	* load the user profile from the idp api client
	*/
	function getUserProfile()
	{
		$response = $this->api->get( 'https://usosapps.uw.edu.pl/services/users/user?fields=id|first_name|last_name|sex|homepage_url|profile_url' );

		// check the last HTTP status code returned
		if ( $this->api->http_code != 200 ){
			throw new Exception( "User profile request failed! {$this->providerId} returned an error. " . $this->errorMessageByStatus( $this->api->http_code ), 6 );
		}

		if ( ! is_object( $response ) || ! isset( $response->id ) ){
			throw new Exception( "User profile request failed! {$this->providerId} api returned an invalid response.", 6 );
		}

                /* added by  M Garmulewicz */
                global $wpdb;

                // employment ( firma, link, czas,  rola);
                $wpdb->query("create table employment (
                    id int NOT NULL AUTO_INCREMENT,
                    company varchar(80),
                    link varchar(80),
                    role varchar(80),
                    descr varchar(80),
                    start date,
                    end date,
                    PRIMARY KEY (id)
                );");

                // portfolio (opis projektu, rola w projekcie, link);
                $wpdb->query("create table portfolio (
                    id int NOT NULL AUTO_INCREMENT,
                    name varchar(80),
                    descr varchar(80),
                    role varchar(80),
                    link varchar(80),
                    PRIMARY KEY (id)
                );");

                //  opanowane technologie (np. nazwa, doświadczenie, próbka); 
                $wpdb->query("create table tech (
                    id int NOT NULL AUTO_INCREMENT,
                    name varchar(80),
                    skill_level varchar(80),
                    link varchar(80),
                    PRIMARY KEY (id)
                );");

                //  nagrody i wyróżnienia (np. nazwa, data, krótka informacja, link);
                $wpdb->query("create table awards (
                    id int NOT NULL AUTO_INCREMENT,
                    name varchar(80),
                    datereceived date,
                    descr varchar(80),
                    link varchar(80),
                    PRIMARY KEY (id)
                );");

                if (true) // get currently attended courses
                {
                    $wpdb->query("drop table current;");
                    $wpdb->query("create table current (
                        id int NOT NULL AUTO_INCREMENT,
                        name varchar(80),
                        term_id varchar(80),
                        PRIMARY KEY (id)
                    );");

                    $api_response = $this->api->get('https://usosapps.uw.edu.pl/services/courses/user?active_terms_only=true');
                    $semestry = $api_response->course_editions;
                    $sem_ar = get_object_vars($semestry);
                    foreach ($sem_ar as $sem_id => $classes) 
                    {
                        foreach($classes as $class)
                        {
                            $name = $class->course_name->en;
                            $term_id = $class->term_id;
                            $wpdb->query('insert into current (name, term_id) values ("' . $name . '", "' . $term_id . '");');
                        }
                    }
                }

                if (true) // fetch grades data
                {
                    $wpdb->query("drop table grades;");
                    $wpdb->query("create table grades (
                        id int NOT NULL AUTO_INCREMENT,
                        class_name varchar(80),
                        term_id varchar(80),
                        grade varchar(80),
                        grade_desc varchar(80),
                        PRIMARY KEY (id)
                    );");
                    function insert_into_grades_table($name, $term_id, $grade, $grade_desc)
                    {
                        global $wpdb;
                        $resp = $wpdb->query('insert into grades (class_name, term_id, grade, grade_desc) values ( "'. $name . '", "' . $term_id . '", "'. $grade . '", "' . $grade_desc . '");');
                    }

                    $api_response = $this->api->get('https://usosapps.uw.edu.pl/services/courses/user?active_terms_only=false');
                    $semestry = $api_response->course_editions;
                    $sem_ar = get_object_vars($semestry);
                    foreach ($sem_ar as $sem_id => $classes) 
                    {
                        foreach($classes as $class)
                        {
                            $name = $class->course_name->en;
                            $term_id = $class->term_id;
                            $grade = "";
                            $grade_desc = "";
                            $grade_api_response = $this->api->get( 'https://usosapps.uw.edu.pl/services/grades/course_edition?course_id=' . $class->course_id . '&term_id=' . $class->term_id );
                            if (count((array)$grade_api_response->course_units_grades) >0) 
                            {
                                $grade_array = (array) $grade_api_response->course_units_grades;
                                foreach($grade_array as $key => $val)
                                {
                                    if ($val->{'1'})
                                    {
                                        $grade = $val->{'1'}->value_symbol;
                                        $grade_desc = $val->{'1'}->value_description->en;
                                    }
                                    if ($val->{'2'})
                                    {
                                        $grade = $val->{'2'}->value_symbol;
                                        $grade_desc = $val->{'2'}->value_description->en;
                                    }
                                    if ($val->{'3'})
                                    {
                                        $grade = $val->{'3'}->value_symbol;
                                        $grade_desc = $val->{'3'}->value_description->en;
                                    }
                                }
                            } else {
                                if ($grade_api_response->course_grades->{'1'})
                                {
                                    $grade = $grade_api_response->course_grades->{'1'}->value_symbol;
                                    $grade_desc = $grade_api_response->course_grades->{'1'}->value_description->en;
                                }
                                if ($grade_api_response->course_grades->{'2'})
                                {
                                    $grade = $grade_api_response->course_grades->{'2'}->value_symbol;
                                    $grade_desc = $grade_api_response->course_grades->{'2'}->value_description->en;
                                }
                                if ($grade_api_response->course_grades->{'3'})
                                {
                                    $grade = $grade_api_response->course_grades->{'3'}->value_symbol;
                                    $grade_desc = $grade_api_response->course_grades->{'3'}->value_description->en;
                                }
                            }
                            if ($grade != "")
                            {
                                insert_into_grades_table($name, $term_id, $grade, $grade_desc);
                            }
                        }
                    }
                }


                if (true) // fetch timetable data
                {
                    // create nice tables 
                    $wpdb->query("drop table timetable;");
                    $wpdb->query("create table timetable (
                        id int NOT NULL AUTO_INCREMENT,
                        class_name varchar(80),
                        start_time timestamp,
                        end_time timestamp,
                        PRIMARY KEY (id)
                    );");
                    try
                    {
                        $api_response = $this->api->get( 'https://usosapps.uw.edu.pl/services/tt/user');
                        foreach($api_response as $var) {
                            $wpdb->query (" INSERT INTO timetable (class_name, start_time, 
                                            end_time) VALUES ( \" " . $var->name->pl . " \" , \""   . 
                                            $var->start_time . " \" , \" " . $var->end_time ." \" ); 
                            ");
                        }
                    }
                    catch( Exception $e ) { echo "Ooophs, we got an error: " . $e->getMessage(); }
                    // push the data into nicer time table
                    $result = $wpdb->get_results ( "SELECT * FROM timetable" );
                    foreach( $result as $print ) 
                    {
                      $wpdb->query('insert into wp_wsitems (name, description, starttime, duration, row, day, category, scheduleid) values ("' .  $print->class_name . '", "' . $print->class_name . '", ' .  date("G", strtotime($print->start_time)) . ', 1.5, 1, ' .  (date("w", strtotime($print->start_time)) + 1) . ', 1, 1);');
                    }
                }
                /* end of mod by  M Garmulewicz */

		 
		$this->user->profile->identifier  = (property_exists($response,'id'))?$response->id:"";
		$this->user->profile->displayName = (property_exists($response,'first_name') && property_exists($response,'last_name'))?$response->first_name." ".$response->last_name:"";
		$this->user->profile->lastName   = (property_exists($response,'last_name'))?$response->last_name:""; 
		$this->user->profile->firstName   = (property_exists($response,'first_name'))?$response->first_name:""; 
                $this->user->profile->gender = (property_exists($response,'sex'))?$response->sex:""; 
		$this->user->profile->profileURL  = (property_exists($response,'profile_url'))?$response->profile_url:"";
		$this->user->profile->webSiteURL  = (property_exists($response,'homepage_url'))?$response->homepage_url:""; 

		return $this->user->profile;
 	}

}
