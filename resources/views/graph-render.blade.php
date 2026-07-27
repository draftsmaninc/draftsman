<!doctype html>
{{-- Static, JS-free render of a saved graph document. Mirrors the DOM the live
     x-flow-schema canvas produces (flow-node/flow-schema-* classes) so the
     compiled frontend CSS styles it identically; edges draw from the SVG paths
     frontv captured at save time. Consumed by humans, and by headless-browser
     screenshots for image export — nothing here waits on JavaScript. --}}
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>{{ $name }} — Draftsman</title>
    <style>{!! $css !!}</style>
    <style>
        body { margin: 0; background: #f8f8f8; }
        .draftsman-render { position: relative; overflow: hidden; }
        /* Static page: nodes aren't draggable, so no grab cursor. */
        .draftsman-render .flow-node { cursor: default; }
    </style>
</head>
<body>
    <div class="draftsman-render flow-container" style="width: {{ $width }}px; height: {{ $height }}px;">
        <div class="flow-viewport" style="position: absolute; inset: 0; transform: translate({{ $offsetX }}px, {{ $offsetY }}px);">
            <div class="flow-edges">
                @foreach ($edges as $edge)
                    <svg class="flow-edge-svg"><g><path fill="none" d="{{ $edge['d'] }}" /></g></svg>
                @endforeach
            </div>
            @foreach ($nodes as $node)
                <div class="flow-node flow-schema-node" style="width: {{ $node['width'] }}px; left: {{ $node['x'] }}px; top: {{ $node['y'] }}px;">
                    <div class="flow-schema-header">
                        <div class="flow-schema-header-title">
                            <span class="flow-schema-header-name">{{ $node['label'] }}</span>
                            <span class="flow-schema-header-namespace">{{ $node['namespace'] }}</span>
                        </div>
                    </div>
                    <div class="flow-schema-body">
                        @foreach ($node['fields'] as $field)
                            <div class="{{ $field['classes'] }}">
                                <div class="flow-schema-handle flow-schema-handle--target flow-handle flow-handle-target"></div>
                                <span class="flow-schema-row-name">{{ $field['name'] }}</span>
                                <span class="flow-schema-row-type">{{ $field['type'] }}</span>
                                <div class="flow-schema-handle flow-schema-handle--source flow-handle flow-handle-source"></div>
                                <div class="flow-schema-handle flow-schema-handle--target flow-schema-handle--mirror flow-handle flow-handle-target"></div>
                                <div class="flow-schema-handle flow-schema-handle--source flow-schema-handle--mirror flow-handle flow-handle-source"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>
