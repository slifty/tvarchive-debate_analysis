<?php
  require_once("config.php");

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

    $curl_url = "https://archive.org/metamgr.php?f=exportIDs&srt=updated&ord=desc&w_mediatype=movies&w_collection=TV-*tvnews*&w_curatestate=!dark%20OR%20NULL&fs_identifier=on&fs_mediatype=on&fs_collection=on&fs_contributor=on&fs_sponsor=on&fs_uploader=on&fs_scancenter=on&fs_curatestate=on&off=0&lim=25&w_identifier=".implode($metamgr_query_array, " OR ");
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

          echo("\n\r".$match_start_date.": ".$match_start_minutes." (=".$program_start_minutes." + ".$match->target_start."/60)");

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

  function generate_raw_csv($glued_results, $file_base) {
    // Define the columns of the CSV
    $file_path = $file_base."_raw.csv";
    $columns = array(
      "debate_media",
      "coverage_media",
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

      // Store the row
      $row = array(
        $match->target_media_id,
        $match->destination_media->external_id,
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

    // FULL
    array(
      "core_programs" => array("KNTV_20160927_010000_NBC_News_Special_2016_Presidential_Debate_1"),
      "comparison_channels" => array(
        // EVENING
        array(
          "code" => "FOXNEWSW", // Fox
          "date" => "20160927",
          "start"=> "161",
          "end"  => "281"
        ),
        array(
          "code" => "MSNBCW",   // MSNBC
          "date" => "20160927",
          "start"=> "161",
          "end"  => "281"
        ),
        array(
          "code" => "CNNW",     // CNN
          "date" => "20160927",
          "start"=> "161",
          "end"  => "281"
        ),
        array(
          "code" => "WPVI",     // ABC
          "date" => "20160927",
          "start"=> "161",
          "end"  => "281"
        ),
        array(
          "code" => "WCAU",      // NBC (local)
          "date" => "20160927",
          "start"=> "161",
          "end"  => "281"
        ),
        array(
          "code" => "KYW",     // CBS
          "date" => "20160927",
          "start"=> "161",
          "end"  => "281"
        ),
        array(
          "code" => "KQED",     // PBS
          "date" => "20160927",
          "start"=> "161",
          "end"  => "281"
        ),
        array(
          "code" => "KSTS",     // Telemundo
          "date" => "20160927",
          "start"=> "161",
          "end"  => "281"
        ),
        array(
          "code" => "WUVP",     // Univision
          "date" => "20160927",
          "start"=> "161",
          "end"  => "281"
        ),

        // MORNING CABLE
        array(
          "code" => "FOXNEWSW", // Fox
          "date" => "20160927",
          "start"=> "660",
          "end"  => "780"
        ),
        array(
          "code" => "MSNBCW",   // MSNBC
          "date" => "20160927",
          "start"=> "600",
          "end"  => "780"
        ),
        array(
          "code" => "CNNW",     // CNN
          "date" => "20160927",
          "start"=> "660",
          "end"  => "780"
        ),
        array(
          "code" => "WPVI",     // ABC
          "date" => "20160927",
          "start"=> "660",
          "end"  => "780"
        ),
        array(
          "code" => "WCAU",      // NBC (local)
          "date" => "20160927",
          "start"=> "660",
          "end"  => "780"
        ),
        array(
          "code" => "KYW",     // CBS
          "date" => "20160927",
          "start"=> "660",
          "end"  => "780"
        ),
        array(
          "code" => "KQED",     // PBS
          "date" => "20160927",
          "start"=> "660",
          "end"  => "780"
        ),
        array(
          "code" => "KSTS",     // Telemundo
          "date" => "20160927",
          "start"=> "660",
          "end"  => "780"
        ),
        array(
          "code" => "WUVP",     // Univision
          "date" => "20160927",
          "start"=> "660",
          "end"  => "780"
        ),

        // MORNING LOCAL
        array(
          "code" => "WMUR",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WBZ",
          "date" => "0160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WCVB",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WFXT",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WHDH",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "KNXV",
          "date" => "20160927",
          "start"=> "630",
          "end"  => "780"
        ),
        array(
          "code" => "KPHO",
          "date" => "20160927",
          "start"=> "630",
          "end"  => "780"
        ),
        array(
          "code" => "KPNX",
          "date" => "20160927",
          "start"=> "630",
          "end"  => "780"
        ),
        array(
          "code" => "KSAZ",
          "date" => "20160927",
          "start"=> "630",
          "end"  => "780"
        ),
        array(
          "code" => "WDJT",
          "date" => "20160927",
          "start"=> "510",
          "end"  => "660"
        ),
        array(
          "code" => "WISN",
          "date" => "20160927",
          "start"=> "510",
          "end"  => "660"
        ),
        array(
          "code" => "WITI",
          "date" => "20160927",
          "start"=> "510",
          "end"  => "660"
        ),
        array(
          "code" => "WTMJ",
          "date" => "20160927",
          "start"=> "510",
          "end"  => "660"
        ),
        array(
          "code" => "KLAS",
          "date" => "20160927",
          "start"=> "630",
          "end"  => "780"
        ),
        array(
          "code" => "KSNV",
          "date" => "20160927",
          "start"=> "630",
          "end"  => "780"
        ),
        array(
          "code" => "KTNV",
          "date" => "20160927",
          "start"=> "630",
          "end"  => "780"
        ),
        array(
          "code" => "KVVU",
          "date" => "20160927",
          "start"=> "630",
          "end"  => "780"
        ),
        array(
          "code" => "KCNC",
          "date" => "20160927",
          "start"=> "570",
          "end"  => "720"
        ),
        array(
          "code" => "KDVR",
          "date" => "20160927",
          "start"=> "570",
          "end"  => "720"
        ),
        array(
          "code" => "KMGH",
          "date" => "20160927",
          "start"=> "570",
          "end"  => "720"
        ),
        array(
          "code" => "KUSA",
          "date" => "20160927",
          "start"=> "570",
          "end"  => "720"
        ),
        array(
          "code" => "WEWS",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WJW",
          "date" => "0160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WKYC",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WOIO",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WFLA",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WFTS",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WTOG",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WTVT",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WLFL",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WNCN",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WRAL",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WRAZ",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "KCRG",
          "date" => "20160927",
          "start"=> "510",
          "end"  => "660"
        ),
        array(
          "code" => "KGAN",
          "date" => "20160927",
          "start"=> "510",
          "end"  => "660"
        ),
        array(
          "code" => "KFXA",
          "date" => "20160927",
          "start"=> "510",
          "end"  => "660"
        ),
        array(
          "code" => "KYW",
          "date" => "0160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WCAU",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WPVI",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "WTXF",
          "date" => "20160927",
          "start"=> "450",
          "end"  => "600"
        ),
        array(
          "code" => "KPIX",
          "date" => "20160927",
          "start"=> "630",
          "end"  => "780"
        ),
        array(
          "code" => "KGO",
          "date" => "0160927",
          "start"=> "630",
          "end"  => "780"
        ),
        array(
          "code" => "KNTV",
          "date" => "20160927",
          "start"=> "630",
          "end"  => "780"
        ),
        array(
          "code" => "KTVU",
          "date" => "20160927",
          "start"=> "630",
          "end"  => "780"
        )
      ),
      "comparison_programs_metamgr" => "",
      "comparison_programs" => array()
    )
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
