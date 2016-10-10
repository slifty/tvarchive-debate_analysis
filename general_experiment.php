<?php
  require_once("config.php");
  date_default_timezone_set("UTC");

  // HELPER DT5K METHODS
  function get_media($media_id) {
    global $dt5k_url;
    $curl_url = $dt5k_url."media/".$media_id;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curl_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $media_output = curl_exec ($ch);
    curl_close ($ch);
    $media = json_decode($media_output);
    return $media;
  }
  function get_task($task_id, $return_object=true) {
    global $dt5k_url;
    $curl_url = $dt5k_url."media_tasks/".$task_id;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curl_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $task_output = curl_exec ($ch);
    curl_close ($ch);
    $task = json_decode($task_output);
    if($return_object)
      return $task;
    return $task_output;
  }
  function get_transcript($program_id, $start, $stop) {
    global $user;
    global $sig;
    $curl_url = "https://archive.org/download/".$program_id."/".$program_id.".cc5.srt?t=".$start."/".$stop;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIE, 'logged-in-user='.$user.';logged-in-sig='.$sig);
    curl_setopt($ch, CURLOPT_URL, $curl_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $server_output = curl_exec ($ch);
    curl_close ($ch);

    $transcript = "";
    $lines = explode(PHP_EOL, $server_output);
    $useful_lines = array();
    foreach($lines as $line) {
      if(trim($line) == "")
        continue;
      if(ctype_digit(substr($line,0,1)))
        continue;
      $useful_lines[] = $line;
    }
    $transcript = implode(" ", $useful_lines);
    return $transcript;
  }

  // Step 1: Create the Project
  function create_project() {
    global $dt5k_url;
    echo("Creating Project\n\r");

    $curl_url = $dt5k_url."projects";
    $post_data = array(
      "name" => "duplitron_analysis-".time()
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curl_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $server_output = curl_exec ($ch);
    curl_close ($ch);
    $project = json_decode($server_output);
    echo("\n\rCreated project ".$project->id);
    $project_id = $project->id;

    return $project_id;
  }

  // Step 2: Load the program IDs for this projet
  function get_programs_by_query($metamgr_query) {
    global $user;
    global $sig;
    echo("\n\rLookup up program IDs from metamgr via query: ".$metamgr_query);

    $curl_url = "https://archive.org/metamgr.php?f=exportIDs&srt=updated&ord=desc&w_mediatype=movies&w_collection=TV-*tvnews*&w_curatestate=!dark%20OR%20NULL&fs_identifier=on&fs_mediatype=on&fs_collection=on&fs_contributor=on&fs_sponsor=on&fs_uploader=on&fs_scancenter=on&fs_curatestate=on&off=0&lim=25&w_identifier=".$metamgr_query;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curl_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Cookie: logged-in-user=".$user.";logged-in-sig=".$sig));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $debate_program_lookup = curl_exec ($ch);
    curl_close ($ch);

    $programs = preg_split('/\s+/', $debate_program_lookup);

    echo("\n\rPrograms Loaded: ". implode(", ", $programs));
    return $programs;
  }

  function get_programs_by_channels($channels) {
    global $user;
    global $sig;

    $metamgr_query_array = array();

    foreach($channels as $channel) {
      $channel_code = $channel['code'];
      $date = $channel['date'];
      $first_hour = floor($channel['start'] / 60);
      $last_hour = ceil($channel['end'] / 60);
      $metamgr_query_array[] = $channel_code."_".$date."_*";
    }

    echo("\n\rLookup up program IDs from metamgr via query: ".implode($metamgr_query_array, " OR "));

    $curl_url = "https://archive.org/metamgr.php?f=exportIDs&srt=updated&ord=desc&w_mediatype=movies&w_collection=TV-*&w_curatestate=!dark%20OR%20NULL&fs_identifier=on&fs_mediatype=on&fs_collection=on&fs_contributor=on&fs_sponsor=on&fs_uploader=on&fs_scancenter=on&fs_curatestate=on&off=0&lim=25&w_identifier=".implode($metamgr_query_array, " OR ");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curl_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Cookie: logged-in-user=".$user.";logged-in-sig=".$sig));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $debate_program_lookup = curl_exec ($ch);
    curl_close ($ch);

    $programs = preg_split('/\s+/', $debate_program_lookup);

    echo("\n\rPrograms Loaded: ". implode(", ", $programs));
    return $programs;
  }

  // Step 3: Save each program as a target in the project
  function save_programs($programs, $project_id) {
    global $dt5k_url;
    echo("\n\rStoring the programs.");

    $tasks = array();
    foreach($programs as $program) {
      if($program == "")
        continue;
      echo("\n\rStoring program ".$program);

      // Create the media
      $curl_url = $dt5k_url."media";
      $program_mp3 = "http://archive.org/download/".$program."/format=MP3";
      $program_afpt = "http://archive.org/compress/".$program."/formats=COLUMBIA%20SPARSE%20FINGERPRINT%20TV&file=/".$program.".zip";
      $post_data = array(
        "project_id" => $project_id,
        "media_path" => $program_mp3,
        "afpt_path" => $program_afpt,
        "external_id" => $program
      );

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $curl_url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $server_output = curl_exec ($ch);
      curl_close ($ch);
      $media = json_decode($server_output);

      $media_id = $media->id;
      echo("\n\rCreated media item: ".$media->id);

      // Register it as a target
      $post_data = array(
        "media_id" => $media_id,
        "type" => "corpus_add"
      );

      $curl_url = $dt5k_url."media_tasks";
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $curl_url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $server_output = curl_exec ($ch);
      curl_close ($ch);
      $task = json_decode($server_output);
      echo("\n\rCreated 'Target Add' task: ".$task->id);
      $tasks[] = $task;
    }
    return $tasks;
  }

  function resolve_tasks($tasks) {
    global $dt5k_url;

    echo("\n\rWaiting for tasks to resolve");
    while(sizeof($tasks) > 0) {
      echo(".");
      sleep(5);
      foreach($tasks as $key => $task) {
        $curl_url = $dt5k_url."media_tasks/".$task->id;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $curl_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec ($ch);
        curl_close ($ch);
        $task = json_decode($server_output);

        if($task->status->code == 3) {
          echo("\n\rFINISHED processing task: ".$task->id."\n\r");
          unset($tasks[$key]);
        }
        if($task->status->code == -1) {
          echo("\n\rERROR processing task: ".$task->id."\n\r");
          unset($tasks[$key]);
        }
      }
    }
    return;
  }

  function compare_program($program, $project_id) {
    global $dt5k_url;

    if($program == "")
      continue;

    // Create the media
    $curl_url = $dt5k_url."media";
    $program_mp3 = "http://archive.org/download/".$program."/format=MP3";
    $program_afpt = "http://archive.org/compress/".$program."/formats=COLUMBIA%20SPARSE%20FINGERPRINT%20TV&file=/".$program.".zip";
    $post_data = array(
      "project_id" => $project_id,
      "media_path" => $program_mp3,
      "afpt_path" => $program_afpt,
      "external_id" => $program
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curl_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $server_output = curl_exec ($ch);
    curl_close ($ch);
    $media = json_decode($server_output);

    $media_id = $media->id;
    echo("\n\rCreated media item: ".$media->id);

    // Run the match job
    $post_data = array(
      "media_id" => $media->id,
      "type" => "full_match"
    );

    $curl_url = $dt5k_url."media_tasks";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curl_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $server_output = curl_exec ($ch);
    curl_close ($ch);
    $task = json_decode($server_output);
    echo("\n\rCreated 'match' task: ".$task->id);

    return $task;
  }

  function load_results($task_id) {
    global $dt5k_url;

    // Get the task
    $task_output = get_task($task_id, false);
    $task = json_decode($task_output);

    echo("\n\rProccessing task: ".$task->id);

    // Get the media
    $media = get_media($task->media_id);

    if($task->status->code == -1)
      echo("TASK FAILED: ".$task->id. " ad ID - ".$media->external_id);

    if($task->status->code != 3)
      return null;

    $external_id = $media->external_id;
    $result_file = "results/".$media->project_id."_".$external_id.".json";
    file_put_contents($result_file, json_encode($task));
    return $task;
  }

  function glue_results($result_sets, $glue_width = 10, $channel_array = array()) {
    $glued_results = array();

    // Iterate over the result sets
    foreach($result_sets as $result_set) {
      $media = get_media($result_set->media_id);
      $program_id = $media->external_id;

      // Load the full list of matches
      $matches = $result_set->result->data->matches->corpus;
      $clip_cache = array();
      foreach($matches as $match) {
        if(!array_key_exists($match->destination_media->external_id, $clip_cache))
          $clip_cache[$match->destination_media->external_id] = array();

        // Skip matches that are too long
        // TODO: figure out a better way to detect false positives
        if($match->duration > 120)
          continue;

        $keep = true;
        if(sizeof($channel_array) > 0) {
          // Skip matches that don't fall in a valid window

          // What hour and date did the program start
          $id_parts = explode("_", $match->destination_media->external_id);
          $channel_code = $id_parts[0];
          $program_start_date = $id_parts[1];
          $program_start_hour = (int)substr($id_parts[2], 0, 2);
          $program_start_minutes = (int)substr($id_parts[2], 2, 2) + $program_start_hour * 60;

          $match_start_date = (int)($program_start_date);
          $match_start_minutes = $program_start_minutes + ceil($match->target_start / 60);
          $match_end_date = (int)($program_start_date);
          $match_end_minutes = $program_start_minutes + ceil($match->target_start / 60);

          // Was there a rollover
          if($match_start_minutes > 1440) {
            $match_start_date++;
            $match_start_minutes -= 1440;
          }
          if($match_end_minutes > 1440) {
            $match_end_date++;
            $match_end_minutes -= 1440;
          }

          // Does this match fall in the range we care about
          $keep = false;
          $start_override = -1;
          $end_override = -1;
          foreach($channel_array as $channel) {

            // Skip the rules for other channels
            if($channel_code != $channel['code'])
              continue;

            // Skip the rules for other dates
            if($channel['date'] != $match_start_date
            && $channel['date'] != $match_end_date)
              continue;

            // This should never be true based on previous rules, but including defensivey
            if($channel['date'] > $match_end_date)
              continue;

            // This should never be true based on previous rules, but including defensivey
            if($channel['date'] < $match_start_date)
              continue;

            if($channel['date'] == $match_start_date
            && $channel['end'] < $match_start_minutes)
              continue;

            if($channel['date'] == $match_end_date
            && $channel['start'] > $match_end_minutes)
              continue;

            if($channel['date'] == $match_end_date)
              $end_override = $channel['end'] * 60;

            if($channel['date'] == $match_start_date)
              $end_override = $channel['start'] * 60;

            $keep = true;
          }
        }
        if($keep) {
          $clip_cache[$match->destination_media->external_id][] = $match;
        }
      }

      // Glue any clips that overlap
      foreach($clip_cache as $destination_results) {

        // First, sort the clips for this destination
        usort($destination_results, function($a, $b) {
          if ($a->start == $b->start) {
                return 0;
            }
            return ($a->start < $b->start) ? -1 : 1;
        });

        // Loop through each clip and see if it overlaps
        $clip_list = $destination_results;
        while(sizeof($clip_list) > 0) {
          $current_clip = array_shift($clip_list);
          $other_clips = array();
          foreach($clip_list as $next_clip) {
            // Is the next clip within the "glue" of the current clip's ending
            // If not, consider it next time (separately)
            if($glue_width == -1
            || $next_clip->start > $current_clip->start + $current_clip->duration + $glue_width
            || $next_clip->target_start > $current_clip->target_start + $current_clip->duration + $glue_width) {
              $other_clips[] = $next_clip;
              continue;
            }

            // Glue the clips together
            $current_clip->duration = ($next_clip->start + $next_clip->duration) - $current_clip->start;
            $current_clip->consecutive_hashes += $next_clip->consecutive_hashes;
          }

          // Add the target program ID to the clip
          $current_clip->target_media_id = $program_id;

          // Tack on the final clip
          $glued_results[] = $current_clip;

          // Now consider the ones we skipped;
          $clip_list = $other_clips;
        }
      }
    }
    return $glued_results;
  }

  function get_parts_from_media_id($media_id) {
    $parts = explode("_", $media_id);
    $channel = array_key_exists(0, $parts)?$parts[0]:"";
    $start_date = array_key_exists(1, $parts)?$parts[1]:"";
    if(strlen($start_date) == 8)
      $start_date = substr($start_date, 4, 2)."/".substr($start_date, 6, 2)."/".substr($start_date, 0, 4);

    $start_time = array_key_exists(2, $parts)?$parts[2]:"";
    if(strlen($start_time) == 6)
      $start_time = substr($start_time, 0, 2).":".substr($start_time, 2, 2).":".substr($start_time, 4, 2);

    $program = implode("_", array_slice($parts, 3));

    $parsed_media = array(
      "channel" => $channel,
      "start_date" => $start_date,
      "start_time" => $start_time,
      "program" => $program
    );

    return $parsed_media;
  }

  print_r(get_parts_from_media_id("KQED_20161010_010000_PBS_NewsHour_Debates_2016_A_Special_Report"));
  die();

  function get_channel_metadata($channel_code) {
    switch($channel_code) {
      case "WMUR":
        return array(
          "location" => "Manchester",
          "channel" => "WMUR",
          "network" => "ABC",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WBZ":
        return array(
          "location" => "Boston",
          "channel" => "WBZ",
          "network" => "CBS",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WCVB":
        return array(
          "location" => "Boston",
          "channel" => "WCVB",
          "network" => "ABC",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WFXT":
        return array(
          "location" => "Boston",
          "channel" => "WFXT",
          "network" => "FOX",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WHDH":
        return array(
          "location" => "Boston",
          "channel" => "WHDH",
          "network" => "NBC",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "KNXV":
        return array(
          "location" => "Phoenix",
          "channel" => "KNXV",
          "network" => "ABC",
          "scope" => "local",
          "time_zone" => "PST"
        );
      case "KPHO":
        return array(
          "location" => "Phoenix",
          "channel" => "KPHO",
          "network" => "CBS",
          "scope" => "local",
          "time_zone" => "PST"
        );
      case "KPNX":
        return array(
          "location" => "Phoenix",
          "channel" => "KPNX",
          "network" => "NBC",
          "scope" => "local",
          "time_zone" => "PST"
        );
      case "KSAZ":
        return array(
          "location" => "Phoenix",
          "channel" => "KSAZ",
          "network" => "FOX",
          "scope" => "local",
          "time_zone" => "PST"
        );
      case "WDJT":
        return array(
          "location" => "Milwaukee",
          "channel" => "WDJT",
          "network" => "CBS",
          "scope" => "local",
          "time_zone" => "CST"
        );
      case "WISN":
        return array(
          "location" => "Milwaukee",
          "channel" => "WISN",
          "network" => "ABC",
          "scope" => "local",
          "time_zone" => "CST"
        );
      case "WITI":
        return array(
          "location" => "Milwaukee",
          "channel" => "WITI",
          "network" => "FOX",
          "scope" => "local",
          "time_zone" => "CST"
        );
      case "WTMJ":
        return array(
          "location" => "Milwaukee",
          "channel" => "WTMJ",
          "network" => "NBC",
          "scope" => "local",
          "time_zone" => "CST"
        );
      case "KLAS":
        return array(
          "location" => "Las Vegas",
          "channel" => "KLAS",
          "network" => "CBS",
          "scope" => "local",
          "time_zone" => "PST"
        );
      case "KSNV":
        return array(
          "location" => "Las Vegas",
          "channel" => "KSNV",
          "network" => "NBC",
          "scope" => "local",
          "time_zone" => "PST"
        );
      case "KTNV":
        return array(
          "location" => "Las Vegas",
          "channel" => "KTNV",
          "network" => "ABC",
          "scope" => "local",
          "time_zone" => "PST"
        );
      case "KVVU":
        return array(
          "location" => "Las Vegas",
          "channel" => "KVVU",
          "network" => "FOX",
          "scope" => "local",
          "time_zone" => "PST"
        );
      case "KCNC":
        return array(
          "location" => "Denver",
          "channel" => "KCNC",
          "network" => "CBS",
          "scope" => "local",
          "time_zone" => "MST"
        );
      case "KDVR":
        return array(
          "location" => "Denver",
          "channel" => "KDVR",
          "network" => "Fox",
          "scope" => "local",
          "time_zone" => "MST"
        );
      case "KMGH":
        return array(
          "location" => "Denver",
          "channel" => "KMGH",
          "network" => "ABC",
          "scope" => "local",
          "time_zone" => "MST"
        );
      case "KUSA":
        return array(
          "location" => "Denver",
          "channel" => "KUSA",
          "network" => "NBC",
          "scope" => "local",
          "time_zone" => "MST"
        );
      case "WEWS":
        return array(
          "location" => "Cleavland",
          "channel" => "WEWS",
          "network" => "ABC",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WJW":
        return array(
          "location" => "Cleavland",
          "channel" => "WJW",
          "network" => "FOX",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WKYC":
        return array(
          "location" => "Cleavland",
          "channel" => "WKYC",
          "network" => "NBC",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WOIO":
        return array(
          "location" => "Cleavland",
          "channel" => "WOIO",
          "network" => "CBS",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WFLA":
        return array(
          "location" => "Tampa",
          "channel" => "WFLA",
          "network" => "NBC",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WFTS":
        return array(
          "location" => "Tampa",
          "channel" => "WFTS",
          "network" => "ABC",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WTOG":
        return array(
          "location" => "Tampa",
          "channel" => "WTOG",
          "network" => "CW",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WTVT":
        return array(
          "location" => "Tampa",
          "channel" => "WTVT",
          "network" => "FOX",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WLFL":
        return array(
          "location" => "RDF/NC",
          "channel" => "WLFL",
          "network" => "CW",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WNCN":
        return array(
          "location" => "RDF/NC",
          "channel" => "WNCN",
          "network" => "CBS",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WRAL":
        return array(
          "location" => "RDF/NC",
          "channel" => "WRAL",
          "network" => "NBC",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WRAZ":
        return array(
          "location" => "RDF/NC",
          "channel" => "WRAZ",
          "network" => "FOX",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "KCRG":
        return array(
          "location" => "Cedar Rapids",
          "channel" => "KCRG",
          "network" => "ABC",
          "scope" => "local",
          "time_zone" => "CST"
        );
      case "KGAN":
        return array(
          "location" => "Cedar Rapids",
          "channel" => "KGAN",
          "network" => "CBS",
          "scope" => "local",
          "time_zone" => "CST"
        );
      case "KFXA":
        return array(
          "location" => "Cedar Rapids",
          "channel" => "KFXA",
          "network" => "FOX",
          "scope" => "local",
          "time_zone" => "CST"
        );
      case "KYW":
        return array(
          "location" => "Philadelphia",
          "channel" => "KYW",
          "network" => "CBS",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WCAU":
        return array(
          "location" => "Philadelphia",
          "channel" => "WCAU",
          "network" => "NBC",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WPVI":
        return array(
          "location" => "Philadelphia",
          "channel" => "WPVI",
          "network" => "ABC",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WTXF":
        return array(
          "location" => "Philadelphia",
          "channel" => "WTXF",
          "network" => "FOX",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WUVP":
        return array(
          "location" => "Philadelphia",
          "channel" => "WUVP",
          "network" => "Univision",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WJLA":
        return array(
          "location" => "DC/VA/MD",
          "channel" => "WJLA",
          "network" => "ABC",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WTTG":
        return array(
          "location" => "DC/VA/MD",
          "channel" => "WTTG",
          "network" => "FOX",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WRC":
        return array(
          "location" => "DC/VA/MD",
          "channel" => "WRC",
          "network" => "NBC",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "WUSA":
        return array(
          "location" => "DC/VA/MD",
          "channel" => "WUSA",
          "network" => "CBS",
          "scope" => "local",
          "time_zone" => "EST"
        );
      case "CNNW":
        return array(
          "location" => "SF/Oakland/SJ",
          "channel" => "CNNW",
          "network" => "CNN",
          "scope" => "cable",
          "time_zone" => "PST"
        );
      case "FOXNEWSW":
        return array(
          "location" => "SF/Oakland/SJ",
          "channel" => "FOXNEWSW",
          "network" => "FOX News",
          "scope" => "cable",
          "time_zone" => "PST"
        );
      case "FBC":
        return array(
          "location" => "SF/Oakland/SJ",
          "channel" => "FBC",
          "network" => "FOX Business",
          "scope" => "cable",
          "time_zone" => "PST"
        );
      case "MSNBCW":
        return array(
          "location" => "SF/Oakland/SJ",
          "channel" => "MSNBCW",
          "network" => "MSNBC",
          "scope" => "cable",
          "time_zone" => "PST"
        );
      case "CSPAN":
        return array(
          "location" => "SF/Oakland/SJ",
          "channel" => "CSPAN",
          "network" => "CSPAN I",
          "scope" => "cable",
          "time_zone" => "PST"
        );
      case "CSPAN2":
        return array(
          "location" => "SF/Oakland/SJ",
          "channel" => "CSPAN2",
          "network" => "CSPAN II",
          "scope" => "cable",
          "time_zone" => "PST"
        );
      case "CNBC":
        return array(
          "location" => "SF/Oakland/SJ",
          "channel" => "CNBC",
          "network" => "CNBC",
          "scope" => "cable",
          "time_zone" => "PST"
        );
      case "KGO":
        return array(
          "location" => "SF/Oakland/SJ",
          "channel" => "KGO",
          "network" => "ABC",
          "scope" => "local",
          "time_zone" => "PST"
        );
      case "KNTV":
        return array(
          "location" => "SF/Oakland/SJ",
          "channel" => "KNTV",
          "network" => "NBC",
          "scope" => "local",
          "time_zone" => "PST"
        );
      case "KPIX":
        return array(
          "location" => "SF/Oakland/SJ",
          "channel" => "KPIX",
          "network" => "CBS",
          "scope" => "local",
          "time_zone" => "PST"
        );
      case "KQED":
        return array(
          "location" => "SF/Oakland/SJ",
          "channel" => "KQED",
          "network" => "PBS",
          "scope" => "local",
          "time_zone" => "PST"
        );
      case "KTVU":
        return array(
          "location" => "SF/Oakland/SJ",
          "channel" => "KTVU",
          "network" => "FOX",
          "scope" => "local",
          "time_zone" => "PST"
        );
      case "BLOOMBERG":
        return array(
          "location" => "SF/Oakland/SJ",
          "channel" => "BLOOMBERG",
          "network" => "Bloomberg",
          "scope" => "cable",
          "time_zone" => "EST"
        );
      case "BETW":
        return array(
          "location" => "SF/Oakland/SJ",
          "channel" => "BETW",
          "network" => "BET",
          "scope" => "cable",
          "time_zone" => "PST"
        );
      default:
        echo("\n\rUnknown Code: ".$channel_code);
        return array(
          "location"=> "",
          "channel" => $channel_code,
          "network" => "",
          "scope"   => "",
          "time_zone" => "UTC"
        );
    }
  }

  function generate_raw_csv($glued_results, $file_base) {
    // Define the columns of the CSV
    $file_path = $file_base."_raw.csv";
    $columns = array(
      "debate_media",
      "coverage_media",
      "channel",
      "network",
      "location",
      "channel_type",
      "program",
      "coverage_time_utc",
      "debate_start_second",
      "coverage_start_second",
      "duration",
      "match_url",
      "transcript"
    );
    $csv_file = fopen($file_path, "w");
    fputcsv($csv_file, $columns);

    // Prepare the results
    foreach($glued_results as $match) {

      // Load the transcript
      $transcript = get_transcript($match->target_media_id, $match->start, $match->start + $match->duration);

      // Get media metadata
      $parsed_destination_media = get_parts_from_media_id($match->destination_media->external_id);
      $channel_metadata = get_channel_metadata($parsed_destination_media['channel']);
      $air_time = strtotime("+".floor($match->target_start / 60)." minutes", strtotime($parsed_destination_media['start_date']." ".$parsed_destination_media['start_time']));
      $air_time = strtotime("+".floor($match->target_start % 60)." seconds", $air_time);
      $air_time = date("m/d/Y H:i:s", $air_time);

      // Store the row
      $row = array(
        $match->target_media_id,
        $match->destination_media->external_id,
        $channel_metadata['channel'],
        $channel_metadata['network'],
        $channel_metadata['location'],
        $channel_metadata['scope'],
        $parsed_destination_media['program'],
        $air_time,
        $match->start,
        $match->target_start,
        $match->duration,
        "https://archive.org/details/".$match->destination_media->external_id."#start/".$match->target_start."/end/".($match->target_start + $match->duration),
        $transcript
      );
      fputcsv($csv_file, $row);
    }
    fclose($csv_file);
  }

  function generate_seconds_csv($glued_results, $file_base) {
    global $program_length_limit;
    $seconds_cache = array(); // this will have one item per second...
    // TODO: This limit should be calculated, not set
    for($x = 0; $x < $program_length_limit; $x++) {
      $seconds_cache[$x] = array(
        "count" => 0,
        "programs" => array()
      );
    }

    // Calculate the seconds coverage
    $max_seconds = 0;
    $program_id = 0;
    foreach($glued_results as $match) {
      // Loop through the seconds and update the seconds cache
      for($x = (int)floor($match->start); $x < ceil($match->start + $match->duration); $x++) {
        $seconds_cache[$x]['count']++;
        $seconds_cache[$x]['programs'][] = $match->destination_media->external_id;
        $max_seconds = max($x, $max_seconds);
      }
      // Pull out the ID of the program
      $program_id = $match->target_media_id;
    }

    // Generate the seconds CSV
    $csv_file_seconds = fopen($file_base."_seconds.csv", "w");
    $columns = array(
      "second",
      "coverage_count",
      "transcript",
      "link"
    );
    fputcsv($csv_file_seconds, $columns);
    for($x = 0; $x < $max_seconds + 1; $x++) {
      $transcript = '';
      if($x % 5 == 0)
        $transcript = get_transcript($program_id, $x, $x + 5);
      $row = array(
        $x,
        $seconds_cache[$x]['count'],
        $transcript,
        "https://archive.org/details/".$program_id."#start/".$x."/end/".($x + 60)
      );
      fputcsv($csv_file_seconds, $row);
    }
    fclose($csv_file_seconds);
  }

  function generate_compressed_csv($glued_results, $file_base) {
    global $program_length_limit;
    $seconds_cache = array(); // this will have one item per second...
    // TODO: This limit should be calculated, not set
    for($x = 0; $x < $program_length_limit; $x++) {
      $seconds_cache[$x] = array(
        "count" => 0,
        "programs" => array()
      );
    }

    // Calculate the seconds coverage
    $max_seconds = 0;
    $program_id = 0;
    foreach($glued_results as $match) {
      // Loop through the seconds and update the seconds cache
      for($x = (int)floor($match->start); $x < ceil($match->start + $match->duration); $x++) {
        $seconds_cache[$x]['count']++;
        $seconds_cache[$x]['programs'][] = $match->destination_media->external_id;
        $max_seconds = max($x, $max_seconds);
      }

      // Pull out the ID of the program
      $program_id = $match->target_media_id;
    }

    // Compile the "condensed" summary
    $csv_file_condensed = fopen($file_base."_condensed.csv", "w");
    $columns = array(
      "start_second",
      "duration",
      "coverage_count",
      "programs",
      "match_url",
      "transcript"
    );
    fputcsv($csv_file_condensed, $columns);

    $cursor = -1;
    $current_count = 0;
    foreach($seconds_cache as $x => $second) {
      $count = $seconds_cache[$x]['count'];

      // Count changed: either starting or stopping a block
      if($count != $current_count) {

        if($current_count == 0) {
          // Starting
          $cursor = $x;
          $current_count = $count;
        } else {
          // Ending
          $transcript = get_transcript($program_id, $cursor, $x);

          $row = array(
            $cursor,
            $x - $cursor,
            $current_count,
            implode(", ", $seconds_cache[$x-1]['programs']), // This could be buggy if the programs shifted
            "https://archive.org/details/".$program_id."#start/".$cursor."/end/".$x,
            $transcript
          );
          fputcsv($csv_file_condensed, $row);
          $cursor = $x;
          $current_count = $count;
        }
      }
    }
    fclose($csv_file_condensed);
  }

  function download_program($program) {
    global $user;
    global $sig;

    $path = dirname(__FILE__)."/videos/".$program.".mp4";
    if($program == "")
      continue;

    // Download the media
    $program_mp4 = "http://archive.org/download/".$program."/".$program.".mp4";

    echo("\n\rDownloading ".$program_mp4);

    $fp = fopen($path, 'w+');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $program_mp4);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Cookie: logged-in-user=".$user.";logged-in-sig=".$sig));
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    fclose($fp);
    curl_close ($ch);
  }

  // Set up the experiments
  $experiments = array(
    // array(
    //   "core_programs" => array("KNTV_20160908_000000_Commander-in-Chief_Forum"),
    //   "comparison_channels" => array(
    //      array(
    //       "code" => "MSNBCW",
    //       "date" => "20160908",
    //       "start"=> "300",
    //       "end"  => "400"
    //      )
    //   ),
    //   "comparison_programs_metamgr" => "",
    //   "comparison_programs" => array()
    // )
    //
    //
    //
    //
    // array(
    //   "core_programs" => array("PolAd_DonaldTrump_z40lb"),
    //   "comparison_channels" => array(),
    //   "comparison_programs_metamgr" => "*_20161008_* OR *_20161007_* OR *_20161009_*",
    //   "comparison_programs" => array()
    // )

    // // Trump Tapes
    // array(
    //   // "core_programs" => array("PolAd_DonaldTrump_HillaryClinton_xz04u"),
    //   "core_programs" => array("PolAd_DonaldTrump_z40lb"),
    //   "comparison_channels" => array(
    //     array(
    //       "code" => "WMUR",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WBZ",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WCVB",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WFXT",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WHDH",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KNXV",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KPHO",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KPNX",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KSAZ",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WDJT",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WISN",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WITI",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTMJ",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KLAS",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KSNV",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KTNV",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KVVU",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KCNC",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KDVR",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KMGH",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KUSA",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WEWS",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WJW",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WKYC",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WOIO",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WFLA",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WFTS",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTOG",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTVT",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WLFL",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WNCN",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WRAL",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WRAZ",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KCRG",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KGAN",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KFXA",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KYW",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WCAU",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WPVI",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTXF",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WUVP",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WJLA",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTTG",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WRC",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WUSA",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "CNNW",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "FOXNEWSW",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "FBC",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "MSNBCW",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "CSPAN",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "CSPAN2",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "CNBC",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KGO",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KNTV",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KPIX",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KQED",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KTVU",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "BLOOMBERG",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "BETW",
    //       "date" => "20161007",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //       array(
    //       "code" => "WMUR",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WBZ",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WCVB",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WFXT",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WHDH",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KNXV",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KPHO",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KPNX",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KSAZ",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WDJT",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WISN",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WITI",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTMJ",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KLAS",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KSNV",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KTNV",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KVVU",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KCNC",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KDVR",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KMGH",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KUSA",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WEWS",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WJW",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WKYC",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WOIO",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WFLA",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WFTS",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTOG",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTVT",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WLFL",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WNCN",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WRAL",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WRAZ",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KCRG",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KGAN",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KFXA",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KYW",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WCAU",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WPVI",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTXF",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WUVP",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WJLA",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTTG",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WRC",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WUSA",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "CNNW",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "FOXNEWSW",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "FBC",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "MSNBCW",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "CSPAN",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "CSPAN2",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "CNBC",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KGO",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KNTV",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KPIX",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KQED",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KTVU",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "BLOOMBERG",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "BETW",
    //       "date" => "20161008",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //       array(
    //       "code" => "WMUR",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WBZ",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WCVB",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WFXT",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WHDH",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KNXV",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KPHO",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KPNX",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KSAZ",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WDJT",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WISN",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WITI",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTMJ",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KLAS",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KSNV",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KTNV",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KVVU",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KCNC",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KDVR",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KMGH",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KUSA",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WEWS",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WJW",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WKYC",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WOIO",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WFLA",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WFTS",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTOG",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTVT",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WLFL",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WNCN",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WRAL",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WRAZ",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KCRG",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KGAN",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KFXA",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KYW",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WCAU",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WPVI",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTXF",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WUVP",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WJLA",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WTTG",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WRC",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "WUSA",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "CNNW",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "FOXNEWSW",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "FBC",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "MSNBCW",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "CSPAN",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "CSPAN2",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "CNBC",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KGO",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KNTV",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KPIX",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KQED",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "KTVU",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "BLOOMBERG",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     ),
    //     array(
    //       "code" => "BETW",
    //       "date" => "20161009",
    //       "start" => "0",
    //       "end" => "1440"
    //     )
    //   ),
    //   "comparison_programs_metamgr" => "",
    //   "comparison_programs" => array()
    // )






    // // VP Debate Morning Shows Only (WSJ)
    // array(
    //   "core_programs" => array("KQED_20161005_010000_PBS_NewsHour_Debates_2016_A_Special_Report"),
    //   "comparison_channels" => array(),
    //   "comparison_programs_metamgr" => "WCAU_20161005_*0_Today OR WPVI_20161005_*0_Good_Morning_America OR KYW_20161005_*0_CBS_This_Morning OR WUVP_20161005_*0_Despierta_America OR FOXNEWSW_20161005_*0_FOX__Friends OR MSNBCW_20161005_*0_Morning_Joe OR CNNW_20161005_*0_New_Day OR CNBC_20161005_*0_Squawk_Box",
    //   "comparison_programs" => array()
    // )

    // Second Pres Debate Evening Coverage Only
    array(
      "core_programs" => array("KQED_20161010_010000_PBS_NewsHour_Debates_2016_A_Special_Report"),
      "comparison_channels" => array(

        // Cable
        array(
          "code" => "CNNW",
          "date" => "20161010",
          "start"=> "159",
          "end"  => "279"
        ),
        array(
          "code" => "FOXNEWSW",
          "date" => "20161010",
          "start"=> "159",
          "end"  => "279"
        ),
        array(
          "code" => "FBC",
          "date" => "20161010",
          "start"=> "159",
          "end"  => "279"
        ),
        array(
          "code" => "MSNBCW",
          "date" => "20161010",
          "start"=> "159",
          "end"  => "279"
        ),
        array(
          "code" => "CSPAN",
          "date" => "20161010",
          "start"=> "159",
          "end"  => "279"
        ),
        array(
          "code" => "CSPAN2",
          "date" => "20161010",
          "start"=> "159",
          "end"  => "279"
        ),
        array(
          "code" => "CNBC",
          "date" => "20161010",
          "start"=> "159",
          "end"  => "279"
        ),
        array(
          "code" => "BLOOMBERG",
          "date" => "20161010",
          "start"=> "159",
          "end"  => "279"
        ),
        array(
          "code" => "BETW",
          "date" => "20161010",
          "start"=> "159",
          "end"  => "279"
        ),

        // Local
        array(
          "code" => "KGO",
          "date" => "20161010",
          "start"=> "159",
          "end"  => "180"
        ),
        array(
          "code" => "KNTV",
          "date" => "20161010",
          "start"=> "159",
          "end"  => "180"
        ),
        array(
          "code" => "KPIX",
          "date" => "20161010",
          "start"=> "159",
          "end"  => "180"
        ),
        array(
          "code" => "KQED",
          "date" => "20161010",
          "start"=> "159",
          "end"  => "180"
        ),
        array(
          "code" => "KTVU",
          "date" => "20161010",
          "start"=> "159",
          "end"  => "180"
        ),
       ),
       "comparison_programs_metamgr" => "",
       "comparison_programs" => array()
     )

    // VP Debate Evening Coverage Only
    // array(
    //   "core_programs" => array("KQED_20161005_010000_PBS_NewsHour_Debates_2016_A_Special_Report"),
    //   "comparison_channels" => array(

    //     // Cable
    //     array(
    //       "code" => "CNNW",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "276"
    //     ),
    //     array(
    //       "code" => "FOXNEWSW",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "276"
    //     ),
    //     array(
    //       "code" => "FBC",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "276"
    //     ),
    //     array(
    //       "code" => "MSNBCW",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "276"
    //     ),
    //     array(
    //       "code" => "CSPAN",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "276"
    //     ),
    //     array(
    //       "code" => "CSPAN2",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "276"
    //     ),
    //     array(
    //       "code" => "CNBC",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "276"
    //     ),
    //     array(
    //       "code" => "BLOOMBERG",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "276"
    //     ),
    //     array(
    //       "code" => "BETW",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "276"
    //     ),

    //     // Local
    //     array(
    //       "code" => "KGO",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KNTV",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KPIX",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KQED",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KTVU",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WMUR",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WBZ",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WCVB",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WFXT",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WHDH",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KNXV",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KPHO",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KPNX",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KSAZ",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WDJT",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WISN",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WITI",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WTMJ",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KLAS",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KSNV",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KTNV",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KVVU",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KCNC",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KDVR",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KMGH",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KUSA",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WEWS",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WJW",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WKYC",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WOIO",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WFLA",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WFTS",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WTOG",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WTVT",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WLFL",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WNCN",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WRAL",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WRAZ",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KCRG",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KGAN",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KFXA",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "KYW",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WCAU",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WPVI",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WTXF",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WUVP",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WJLA",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WTTG",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WRC",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     ),
    //     array(
    //       "code" => "WUSA",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "180"
    //     )
    //   ),
    //   "comparison_programs_metamgr" => "",
    //   "comparison_programs" => array()
    // )


    // // VP Debate 24h local
    // array(
    //   "core_programs" => array("KQED_20161005_010000_PBS_NewsHour_Debates_2016_A_Special_Report"),
    //   "comparison_channels" => array(
    //     array(
    //       "code" => "KGO",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KGO",
    //       "date" => "20161006",
    //       "start"=> "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KNTV",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KNTV",
    //       "date" => "20161006",
    //       "start"=> "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KPIX",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KPIX",
    //       "date" => "20161006",
    //       "start"=> "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KQED",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KQED",
    //       "date" => "20161006",
    //       "start"=> "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KTVU",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KTVU",
    //       "date" => "20161006",
    //       "start"=> "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WMUR",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WMUR",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WBZ",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WBZ",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WCVB",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WCVB",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WFXT",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WFXT",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WHDH",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WHDH",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KNXV",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KNXV",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KPHO",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KPHO",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KPNX",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KPNX",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KSAZ",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KSAZ",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WDJT",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WDJT",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WISN",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WISN",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WITI",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WITI",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WTMJ",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WTMJ",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KLAS",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KLAS",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KSNV",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KSNV",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KTNV",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KTNV",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KVVU",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KVVU",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KCNC",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KCNC",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KDVR",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KDVR",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KMGH",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KMGH",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KUSA",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KUSA",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WEWS",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WEWS",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WJW",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WJW",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WKYC",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WKYC",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WOIO",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WOIO",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WFLA",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WFLA",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WFTS",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WFTS",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WTOG",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WTOG",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WTVT",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WTVT",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WLFL",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WLFL",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WNCN",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WNCN",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WRAL",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WRAL",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WRAZ",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WRAZ",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KCRG",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KCRG",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KGAN",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KGAN",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KFXA",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KFXA",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "KYW",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "KYW",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WCAU",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WCAU",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WPVI",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WPVI",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WTXF",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WTXF",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WUVP",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WUVP",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WJLA",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WJLA",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WTTG",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WTTG",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WRC",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WRC",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "WUSA",
    //       "date" => "20161005",
    //       "start" => "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "WUSA",
    //       "date" => "20161006",
    //       "start" => "0",
    //       "end"  => "240"
    //     )
    //   ),
    //   "comparison_programs_metamgr" => "",
    //   "comparison_programs" => array()
    // )



    // // NYTimes
    // array(
    //   "core_programs" => array("KNTV_20160927_010000_NBC_News_Special_2016_Presidential_Debate_1"),
    //   "comparison_programs_metamgr" => "",
    //   "comparison_programs" => array(),
    //   "comparison_channels" => array(

    //     // CNN
    //     array(
    //       "code" => "CNNW",
    //       "date" => "20160927",
    //       "start"=> "161", // 10:41 PM
    //       "end"  => "300"  // 1:00 AM
    //     ),
    //     array(
    //       "code" => "CNNW",
    //       "date" => "20160927",
    //       "start"=> "480", // 5:00am
    //       "end"  => "1440"  // 9:00 PM
    //     ),
    //     array(
    //       "code" => "CNNW",
    //       "date" => "20160928",
    //       "start"=> "0", // 9:00 Pm
    //       "end"  => "180"  // 12:00 AM
    //     ),

    //     // FOX
    //     array(
    //       "code" => "FOXNEWSW",
    //       "date" => "20160927",
    //       "start"=> "161",  // 10:41 PM
    //       "end"  => "360"   // 2:00 AM
    //     ),
    //     array(
    //       "code" => "FOXNEWSW",
    //       "date" => "20160927",
    //       "start"=> "480",  // 4:00 AM
    //       "end"  => "1440"   // 9:00 PM
    //     ),
    //     array(
    //       "code" => "FOXNEWSW",
    //       "date" => "20160928",
    //       "start"=> "0", // 9:00 PM
    //       "end"  => "120"  // 11:00 PM
    //     ),

    //     // MSNBC
    //     array(
    //       "code" => "MSNBCW",
    //       "date" => "20160927",
    //       "start"=> "161",  // 10:41 PM
    //       "end"  => "360"   // 2:00 AM
    //     ),
    //     array(
    //       "code" => "MSNBCW",
    //       "date" => "20160927",
    //       "start"=> "540",  // 5:00 AM
    //       "end"  => "1440"  // 9:00 PM
    //     ),
    //     array(
    //       "code" => "MSNBCW",
    //       "date" => "20160928",
    //       "start"=> "0",    // 9:00 PM
    //       "end"  => "150"  // 11:30 PM
    //     ),
    //   ),
    //   "comparison_programs_metamgr" => "",
    //   "comparison_programs" => array()
    // )


    // ANNENBURG
    // array(
    //   "core_programs" => array("KNTV_20160927_010000_NBC_News_Special_2016_Presidential_Debate_1"),
    //   "comparison_programs_metamgr" => "",
    //   "comparison_programs" => array(),
    //   "comparison_channels" => array(

    //     // EVENING
    //     array(
    //       "code" => "FOXNEWSW", // Fox
    //       "date" => "20160927",
    //       "start"=> "161",  // 10:41 PM
    //       "end"  => "281"   // 12:41 PM
    //     ),
    //     array(
    //       "code" => "MSNBCW",   // MSNBC
    //       "date" => "20160927",
    //       "start"=> "161",  // 10:41 PM
    //       "end"  => "281"   // 12:41 PM
    //     ),
    //     array(
    //       "code" => "CNNW",     // CNN
    //       "date" => "20160927",
    //       "start"=> "161",  // 10:41 PM
    //       "end"  => "281"   // 12:41 PM
    //     ),
    //     array(
    //       "code" => "WPVI",     // ABC
    //       "date" => "20160927",
    //       "start"=> "161",  // 10:41 PM
    //       "end"  => "281"   // 12:41 PM
    //     ),
    //     array(
    //       "code" => "WCAU",      // NBC (local)
    //       "date" => "20160927",
    //       "start"=> "161",  // 10:41 PM
    //       "end"  => "281"   // 12:41 PM
    //     ),
    //     array(
    //       "code" => "KYW",     // CBS
    //       "date" => "20160927",
    //       "start"=> "161",  // 10:41 PM
    //       "end"  => "281"   // 12:41 PM
    //     ),
    //     array(
    //       "code" => "KQED",     // PBS
    //       "date" => "20160927",
    //       "start"=> "161",  // 10:41 PM
    //       "end"  => "281"   // 12:41 PM
    //     ),
    //     array(
    //       "code" => "KSTS",     // Telemundo
    //       "date" => "20160927",
    //       "start"=> "161",  // 10:41 PM
    //       "end"  => "281"   // 12:41 PM
    //     ),
    //     array(
    //       "code" => "WUVP",     // Univision
    //       "date" => "20160927",
    //       "start"=> "161",  // 10:41 PM
    //       "end"  => "281"   // 12:41 PM
    //     ),


    //     // MORNING
    //     array(
    //       "code" => "FOXNEWSW", // Fox
    //       "date" => "20160927",
    //       "start"=> "600",  // 6:00 AM
    //       "end"  => "780"   // 9:00 AM
    //     ),
    //     array(
    //       "code" => "MSNBCW",   // MSNBC
    //       "date" => "20160927",
    //       "start"=> "600",  // 6:00 AM
    //       "end"  => "780"   // 9:00 AM
    //     ),
    //     array(
    //       "code" => "CNNW",     // CNN
    //       "date" => "20160927",
    //       "start"=> "600",  // 6:00 AM
    //       "end"  => "780"   // 9:00 AM
    //     ),
    //     array(
    //       "code" => "WPVI",     // ABC
    //       "date" => "20160927",
    //       "start"=> "600",  // 6:00 AM
    //       "end"  => "780"   // 9:00 AM
    //     ),
    //     array(
    //       "code" => "WCAU",      // NBC (local)
    //       "date" => "20160927",
    //       "start"=> "600",  // 6:00 AM
    //       "end"  => "780"   // 9:00 AM
    //     ),
    //     array(
    //       "code" => "KYW",     // CBS
    //       "date" => "20160927",
    //       "start"=> "600",  // 6:00 AM
    //       "end"  => "780"   // 9:00 AM
    //     ),
    //     array(
    //       "code" => "KQED",     // PBS
    //       "date" => "20160927",
    //       "start"=> "600",  // 6:00 AM
    //       "end"  => "780"   // 9:00 AM
    //     ),
    //     array(
    //       "code" => "KSTS",     // Telemundo
    //       "date" => "20160927",
    //       "start"=> "600",  // 6:00 AM
    //       "end"  => "780"   // 9:00 AM
    //     ),
    //     array(
    //       "code" => "WUVP",     // Univision
    //       "date" => "20160927",
    //       "start"=> "600",  // 6:00 AM
    //       "end"  => "780"   // 9:00 AM
    //     )
    //   ),
    //   "comparison_programs_metamgr" => "",
    //   "comparison_programs" => array()
    // )

    // // Presidential #1 FULL
    // array(
    //   "core_programs" => array("KNTV_20160927_010000_NBC_News_Special_2016_Presidential_Debate_1"),
    //   "comparison_channels" => array(
    //     // // EVENING
    //     // array(
    //     //   "code" => "FOXNEWSW", // Fox
    //     //   "date" => "20160927",
    //     //   "start"=> "161",
    //     //   "end"  => "281"
    //     // ),
    //     // array(
    //     //   "code" => "MSNBCW",   // MSNBC
    //     //   "date" => "20160927",
    //     //   "start"=> "161",
    //     //   "end"  => "281"
    //     // ),
    //     // array(
    //     //   "code" => "CNNW",     // CNN
    //     //   "date" => "20160927",
    //     //   "start"=> "161",
    //     //   "end"  => "281"
    //     // ),
    //     // array(
    //     //   "code" => "WPVI",     // ABC
    //     //   "date" => "20160927",
    //     //   "start"=> "161",
    //     //   "end"  => "281"
    //     // ),
    //     // array(
    //     //   "code" => "WCAU",      // NBC (local)
    //     //   "date" => "20160927",
    //     //   "start"=> "161",
    //     //   "end"  => "281"
    //     // ),
    //     // array(
    //     //   "code" => "KYW",     // CBS
    //     //   "date" => "20160927",
    //     //   "start"=> "161",
    //     //   "end"  => "281"
    //     // ),
    //     // array(
    //     //   "code" => "KQED",     // PBS
    //     //   "date" => "20160927",
    //     //   "start"=> "161",
    //     //   "end"  => "281"
    //     // ),
    //     // array(
    //     //   "code" => "KSTS",     // Telemundo
    //     //   "date" => "20160927",
    //     //   "start"=> "161",
    //     //   "end"  => "281"
    //     // ),
    //     // array(
    //     //   "code" => "WUVP",     // Univision
    //     //   "date" => "20160927",
    //     //   "start"=> "161",
    //     //   "end"  => "281"
    //     // ),

    //     // // MORNING CABLE
    //     // array(
    //     //   "code" => "FOXNEWSW", // Fox
    //     //   "date" => "20160927",
    //     //   "start"=> "660",
    //     //   "end"  => "780"
    //     // ),
    //     // array(
    //     //   "code" => "MSNBCW",   // MSNBC
    //     //   "date" => "20160927",
    //     //   "start"=> "600",
    //     //   "end"  => "780"
    //     // ),
    //     // array(
    //     //   "code" => "CNNW",     // CNN
    //     //   "date" => "20160927",
    //     //   "start"=> "660",
    //     //   "end"  => "780"
    //     // ),
    //     // array(
    //     //   "code" => "WPVI",     // ABC
    //     //   "date" => "20160927",
    //     //   "start"=> "660",
    //     //   "end"  => "780"
    //     // ),
    //     // array(
    //     //   "code" => "WCAU",      // NBC (local)
    //     //   "date" => "20160927",
    //     //   "start"=> "660",
    //     //   "end"  => "780"
    //     // ),
    //     // array(
    //     //   "code" => "KYW",     // CBS
    //     //   "date" => "20160927",
    //     //   "start"=> "660",
    //     //   "end"  => "780"
    //     // ),
    //     // array(
    //     //   "code" => "KQED",     // PBS
    //     //   "date" => "20160927",
    //     //   "start"=> "660",
    //     //   "end"  => "780"
    //     // ),
    //     // array(
    //     //   "code" => "KSTS",     // Telemundo
    //     //   "date" => "20160927",
    //     //   "start"=> "660",
    //     //   "end"  => "780"
    //     // ),
    //     // array(
    //     //   "code" => "WUVP",     // Univision
    //     //   "date" => "20160927",
    //     //   "start"=> "660",
    //     //   "end"  => "780"
    //     // ),

    //     // MORNING LOCAL
    //     array(
    //       "code" => "WMUR",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WBZ",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WCVB",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WFXT",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WHDH",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "KNXV",
    //       "date" => "20160927",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KPHO",
    //       "date" => "20160927",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KPNX",
    //       "date" => "20160927",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KSAZ",
    //       "date" => "20160927",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "WDJT",
    //       "date" => "20160927",
    //       "start" => "600",
    //       "end" => "720"
    //     ),
    //     array(
    //       "code" => "WISN",
    //       "date" => "20160927",
    //       "start" => "600",
    //       "end" => "720"
    //     ),
    //     array(
    //       "code" => "WITI",
    //       "date" => "20160927",
    //       "start" => "600",
    //       "end" => "720"
    //     ),
    //     array(
    //       "code" => "WTMJ",
    //       "date" => "20160927",
    //       "start" => "600",
    //       "end" => "720"
    //     ),
    //     array(
    //       "code" => "KLAS",
    //       "date" => "20160927",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KSNV",
    //       "date" => "20160927",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KTNV",
    //       "date" => "20160927",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KVVU",
    //       "date" => "20160927",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KCNC",
    //       "date" => "20160927",
    //       "start" => "660",
    //       "end" => "780"
    //     ),
    //     array(
    //       "code" => "KDVR",
    //       "date" => "20160927",
    //       "start" => "660",
    //       "end" => "780"
    //     ),
    //     array(
    //       "code" => "KMGH",
    //       "date" => "20160927",
    //       "start" => "660",
    //       "end" => "780"
    //     ),
    //     array(
    //       "code" => "KUSA",
    //       "date" => "20160927",
    //       "start" => "660",
    //       "end" => "780"
    //     ),
    //     array(
    //       "code" => "WEWS",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WJW",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WKYC",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WOIO",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WFLA",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WFTS",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WTOG",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WTVT",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WLFL",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WNCN",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WRAL",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WRAZ",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "KCRG",
    //       "date" => "20160927",
    //       "start" => "600",
    //       "end" => "720"
    //     ),
    //     array(
    //       "code" => "KGAN",
    //       "date" => "20160927",
    //       "start" => "600",
    //       "end" => "720"
    //     ),
    //     array(
    //       "code" => "KFXA",
    //       "date" => "20160927",
    //       "start" => "600",
    //       "end" => "720"
    //     ),
    //     array(
    //       "code" => "KYW",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WCAU",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WPVI",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WTXF",
    //       "date" => "20160927",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "KPIX",
    //       "date" => "20160927",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KGO",
    //       "date" => "20160927",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KNTV",
    //       "date" => "20160927",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KTVU",
    //       "date" => "20160927",
    //       "start" => "720",
    //       "end" => "840"
    //     )
    //   ),
    //   "comparison_programs_metamgr" => "",
    //   "comparison_programs" => array()
    // )


    // // VP 24h Cable
    // array(
    //   "core_programs" => array("KQED_20161005_010000_PBS_NewsHour_Debates_2016_A_Special_Report"),
    //   "comparison_channels" => array(
    //     array(
    //       "code" => "CNNW",
    //       "date" => "20161005",
    //       "start"=> "156", // 10:36 EST
    //       "end"  => "300"  // 1:00 AM EST
    //     ),
    //     array(
    //       "code" => "CNNW",
    //       "date" => "20161005",
    //       "start"=> "393", // 2:33 AM EST
    //       "end"  => "1440"  // 8:00 PM EST
    //     ),
    //     array(
    //       "code" => "CNNW",
    //       "date" => "20161006",
    //       "start"=> "0", // 8:00 PM EST
    //       "end"  => "240"// 12:00 AM EST
    //     ),
    //     array(
    //       "code" => "FOXNEWSW",
    //       "date" => "20161005",
    //       "start"=> "156", // 10:36 PM EST
    //       "end"  => "360"  // 2:00 AM EST
    //     ),
    //     array(
    //       "code" => "FOXNEWSW",
    //       "date" => "20161005",
    //       "start"=> "456", // 3:36 AM EST
    //       "end"  => "1440"  // 8:00 PM EST
    //     ),
    //     array(
    //       "code" => "FOXNEWSW",
    //       "date" => "20161006",
    //       "start"=> "0", // 8:00 PM EST
    //       "end"  => "240"// 12:00 AM EST
    //     ),
    //     array(
    //       "code" => "FBC",
    //       "date" => "20161005",
    //       "start"=> "156", // 10:36 EST
    //       "end"  => "300"  // 1:00 AM EST
    //     ),
    //     array(
    //       "code" => "FBC",
    //       "date" => "20161005",
    //       "start"=> "396", // 2:36 AM EST
    //       "end"  => "1440"  // 8:00 PM EST
    //     ),
    //     array(
    //       "code" => "FBC",
    //       "date" => "20161006",
    //       "start"=> "0", // 8:00 PM EST
    //       "end"  => "240"  // 12:00 AM EST
    //     ),
    //     array(
    //       "code" => "MSNBCW",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "300"  // 1:00 AM EST
    //     ),
    //     array(
    //       "code" => "MSNBCW",
    //       "date" => "20161005",
    //       "start"=> "397", // 2:37 AM EST
    //       "end"  => "1440" // 8:00 PM EST
    //     ),
    //     array(
    //       "code" => "MSNBCW",
    //       "date" => "20161005",
    //       "start"=> "0",   // 8:00 PM EST
    //       "end"  => "240"  // 12:00 AM EST
    //     ),
    //     array(
    //       "code" => "CSPAN",
    //       "date" => "20161005",
    //       "start"=> "156", // 10:36 EST
    //       "end"  => "211"  // 11:31 EST
    //     ),
    //     array(
    //       "code" => "CSPAN",
    //       "date" => "20161005",
    //       "start"=> "307", // 1:07 EST
    //       "end"  => "370"  // 2:10 EST
    //     ),
    //     array(
    //       "code" => "CSPAN",
    //       "date" => "20161005",
    //       "start"=> "463", // 3:43 EST
    //       "end"  => "525"  // 4:45 EST
    //     ),
    //     array(
    //       "code" => "CSPAN",
    //       "date" => "20161005",
    //       "start"=> "620", // 6:20 AM EST
    //       "end"  => "1440"  // 8:00 EST
    //     ),
    //     array(
    //       "code" => "CSPAN",
    //       "date" => "20161006",
    //       "start"=> "0", // 8:00 PM EST
    //       "end"  => "240" // 12:00 EST
    //     ),
    //     array(
    //       "code" => "CSPAN2",
    //       "date" => "20161005",
    //       "start"=> "156", // 10:36 AM EST
    //       "end"  => "1440" // 8:00 PM EST
    //     ),
    //     array(
    //       "code" => "CSPAN2",
    //       "date" => "20161006",
    //       "start"=> "0", // 8:00 PM EST
    //       "end"  => "240" // 12:00 AM EST
    //     ),
    //     array(
    //       "code" => "CNBC",
    //       "date" => "20161005",
    //       "start"=> "156", // 10:36 PM EST
    //       "end"  => "1440" // 8:00 AM EST
    //     ),
    //     array(
    //       "code" => "CNBC",
    //       "date" => "20161006",
    //       "start"=> "0",
    //       "end"  => "240" // 12:00 AM EST
    //     ),
    //     array(
    //       "code" => "BLOOMBERG",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "BLOOMBERG",
    //       "date" => "20161006",
    //       "start"=> "0",
    //       "end"  => "240"
    //     ),
    //     array(
    //       "code" => "BETW",
    //       "date" => "20161005",
    //       "start"=> "156",
    //       "end"  => "1440"
    //     ),
    //     array(
    //       "code" => "BETW",
    //       "date" => "20161006",
    //       "start"=> "0",
    //       "end"  => "240"
    //     ),
    //   ),
    //   "comparison_programs_metamgr" => "",
    //   "comparison_programs" => array()
    // )


    // VP NIGHT FULL
    // array(
    //   "core_programs" => array("KQED_20161005_010000_PBS_NewsHour_Debates_2016_A_Special_Report"),
    //   "comparison_channels" => array(
    //     // EVENING
    //     array(
    //       "code" => "FOXNEWSW", // Fox
    //       "date" => "20161005",
    //       "start"=> "161",
    //       "end"  => "281"
    //     ),
    //     array(
    //       "code" => "MSNBCW",   // MSNBC
    //       "date" => "20161005",
    //       "start"=> "161",
    //       "end"  => "281"
    //     ),
    //     array(
    //       "code" => "CNNW",     // CNN
    //       "date" => "20161005",
    //       "start"=> "161",
    //       "end"  => "281"
    //     ),
    //     array(
    //       "code" => "WPVI",     // ABC
    //       "date" => "20161005",
    //       "start"=> "161",
    //       "end"  => "281"
    //     ),
    //     array(
    //       "code" => "WCAU",      // NBC (local)
    //       "date" => "20161005",
    //       "start"=> "161",
    //       "end"  => "281"
    //     ),
    //     array(
    //       "code" => "KYW",     // CBS
    //       "date" => "20161005",
    //       "start"=> "161",
    //       "end"  => "281"
    //     ),
    //     array(
    //       "code" => "KQED",     // PBS
    //       "date" => "20161005",
    //       "start"=> "161",
    //       "end"  => "281"
    //     ),
    //     array(
    //       "code" => "KSTS",     // Telemundo
    //       "date" => "20161005",
    //       "start"=> "161",
    //       "end"  => "281"
    //     ),
    //     array(
    //       "code" => "WUVP",     // Univision
    //       "date" => "20161005",
    //       "start"=> "161",
    //       "end"  => "281"
    //     ),
    //   ),
    //   "comparison_programs_metamgr" => "",
    //   "comparison_programs" => array()
    // )

    // // VP Morning Full
    // array(
    //   "core_programs" => array("KQED_20161005_010000_PBS_NewsHour_Debates_2016_A_Special_Report"),
    //   "comparison_channels" => array(
    //     // MORNING LOCAL
    //     array(
    //       "code" => "WMUR",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WBZ",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WCVB",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WFXT",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WHDH",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "KNXV",
    //       "date" => "20161005",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KPHO",
    //       "date" => "20161005",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KPNX",
    //       "date" => "20161005",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KSAZ",
    //       "date" => "20161005",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "WDJT",
    //       "date" => "20161005",
    //       "start" => "600",
    //       "end" => "720"
    //     ),
    //     array(
    //       "code" => "WISN",
    //       "date" => "20161005",
    //       "start" => "600",
    //       "end" => "720"
    //     ),
    //     array(
    //       "code" => "WITI",
    //       "date" => "20161005",
    //       "start" => "600",
    //       "end" => "720"
    //     ),
    //     array(
    //       "code" => "WTMJ",
    //       "date" => "20161005",
    //       "start" => "600",
    //       "end" => "720"
    //     ),
    //     array(
    //       "code" => "KLAS",
    //       "date" => "20161005",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KSNV",
    //       "date" => "20161005",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KTNV",
    //       "date" => "20161005",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KVVU",
    //       "date" => "20161005",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KCNC",
    //       "date" => "20161005",
    //       "start" => "660",
    //       "end" => "780"
    //     ),
    //     array(
    //       "code" => "KDVR",
    //       "date" => "20161005",
    //       "start" => "660",
    //       "end" => "780"
    //     ),
    //     array(
    //       "code" => "KMGH",
    //       "date" => "20161005",
    //       "start" => "660",
    //       "end" => "780"
    //     ),
    //     array(
    //       "code" => "KUSA",
    //       "date" => "20161005",
    //       "start" => "660",
    //       "end" => "780"
    //     ),
    //     array(
    //       "code" => "WEWS",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WJW",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WKYC",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WOIO",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WFLA",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WFTS",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WTOG",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WTVT",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WLFL",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WNCN",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WRAL",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WRAZ",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "KCRG",
    //       "date" => "20161005",
    //       "start" => "600",
    //       "end" => "720"
    //     ),
    //     array(
    //       "code" => "KGAN",
    //       "date" => "20161005",
    //       "start" => "600",
    //       "end" => "720"
    //     ),
    //     array(
    //       "code" => "KFXA",
    //       "date" => "20161005",
    //       "start" => "600",
    //       "end" => "720"
    //     ),
    //     array(
    //       "code" => "KYW",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WCAU",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WPVI",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "WTXF",
    //       "date" => "20161005",
    //       "start" => "540",
    //       "end" => "660"
    //     ),
    //     array(
    //       "code" => "KPIX",
    //       "date" => "20161005",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KGO",
    //       "date" => "20161005",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KNTV",
    //       "date" => "20161005",
    //       "start" => "720",
    //       "end" => "840"
    //     ),
    //     array(
    //       "code" => "KTVU",
    //       "date" => "20161005",
    //       "start" => "720",
    //       "end" => "840"
    //     )
    //   ),
    //   "comparison_programs_metamgr" => "",
    //   "comparison_programs" => array()
    // )
  );

  // Go through each experiment and generate the data
  foreach($experiments as $experiment) {

    // Step 1: Create the Project
    $project_id = create_project();

    // Step 2: Load the comparison programs
    $comparison_programs = array();
    if($experiment["comparison_programs_metamgr"] != "") {
      $comparison_programs = get_programs_by_query($experiment["comparison_programs_metamgr"]);
    } elseif(sizeof($experiment["comparison_channels"]) > 0) {
      $comparison_programs = get_programs_by_channels($experiment['comparison_channels']);
    } else {
      $comparison_programs = $experiment["comparison_programs"];
    }

    // Step 3: Save the comparison programs
    $add_tasks = save_programs($comparison_programs, $project_id);

    // Step 4: Wait for the programs to save
    resolve_tasks($add_tasks);

    // Step 5: Run the comparison
    $comparison_tasks = array();
    foreach($experiment['core_programs'] as $core_program) {
      $comparison_tasks[] = compare_program($core_program, $project_id);
    }

    // Step 6: Wait for the comparisons so finish
    resolve_tasks($comparison_tasks);

    // Step 7: Load the results into json and memory
    $comparison_result_sets = array();
    foreach($comparison_tasks as $comparison_task) {
      $comparison_result_sets[] = load_results($comparison_task->id);
    }

    // Step 8: Glue the result sets
    $unglued_results = glue_results($comparison_result_sets, -1, $experiment['comparison_channels']);
    $glued_results = glue_results($comparison_result_sets, 10, $experiment['comparison_channels']);

    // Step 9: Compile the results CSVs
    $file_base = time();
    generate_raw_csv($unglued_results, $file_base."_unglued");
    generate_raw_csv($glued_results, $file_base);
    generate_compressed_csv($glued_results, $file_base);
    generate_seconds_csv($glued_results, $file_base);

    // Step 10: Download the programs
    foreach($experiment['core_programs'] as $core_program) {
      download_program($core_program);
    }
  }
?>
