
<?php
        if($tem==null){
            echo '#ff0000';
        } else{
            $start_rota =\Carbon\Carbon::parse($tem[0]);

            $end_rota = \Carbon\Carbon::parse($tem[1]);

            $difference_rota = $start_rota->diffInMinutes($end_rota);

            if($difference_rota == $difference_rotadays){
                echo '#4169e1';
            } else if($difference_rota<$difference_rotadays){
                echo '#87cefa';
            } else if($difference_rotadays==0){
                echo '#ff0000';
            }
        }


?>