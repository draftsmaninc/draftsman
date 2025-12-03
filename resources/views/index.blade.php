@php
    ob_start();
@endphp
@include('draftsman::front')
@php
    $front = ob_get_contents();
    ob_end_clean();
    $pattern = '/(href|src)=(["\'])(\/)(_next)\//im';
    $replacement = '/$1=$2$3draftsman$3$4/';
    $front = preg_replace($pattern, $replacement, $front);
    echo $front;
@endphp
