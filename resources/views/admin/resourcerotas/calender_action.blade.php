<?php
    if($days->start_time==null){
        echo 'On Leave';
    } else{
        echo 'From:'.$days->start_time.' To:'.$days->end_time;
    }
?>

