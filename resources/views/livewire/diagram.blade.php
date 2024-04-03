<div>
  @assets  
  <script src="{{ asset('js/go.js') }}"></script>
  @endassets
    <div id="allSampleContent" class="p-4 w-full">
    
    <style type="text/css">
      #myOverviewDiv {
        position: absolute;
        width: 200px;
        height: 100px;
        top: 10px;
        left: 10px;
        background-color: #f2f2f2;
        z-index: 300; /* make sure its in front */
        border: solid 1px #7986cb;
      }
    </style>
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500" rel="stylesheet" type="text/css">
    @script
      <script id="code">
      function init() {
    
        // Since 2.2 you can also author concise templates with method chaining instead of GraphObject.make
        // For details, see https://gojs.net/latest/intro/buildingObjects.html
        const $ = go.GraphObject.make;  // for conciseness in defining templates
    
        // some constants that will be reused within templates
        var mt8 = new go.Margin(8, 0, 0, 0);
        var mr8 = new go.Margin(0, 8, 0, 0);
        var ml8 = new go.Margin(0, 0, 0, 8);
        var roundedRectangleParams = {
          parameter1: 2,  // set the rounded corner
          spot1: go.Spot.TopLeft, spot2: go.Spot.BottomRight  // make content go all the way to inside edges of rounded corners
        };
    
        myDiagram =
          new go.Diagram("myDiagramDiv",  // the DIV HTML element
            {
              // Put the diagram contents at the top center of the viewport
              initialDocumentSpot: go.Spot.Top,
              initialViewportSpot: go.Spot.Top,
              // OR: Scroll to show a particular node, once the layout has determined where that node is
              // "InitialLayoutCompleted": e => {
              //  var node = e.diagram.findNodeForKey(28);
              //  if (node !== null) e.diagram.commandHandler.scrollToPart(node);
              // },
              layout:
                $(go.TreeLayout,  // use a TreeLayout to position all of the nodes
                  {
                    isOngoing: false,  // don't relayout when expanding/collapsing panels
                    treeStyle: go.TreeLayout.StyleLastParents,
                    // properties for most of the tree:
                    angle: 90,
                    layerSpacing: 80,
                    // properties for the "last parents":
                    alternateAngle: 0,
                    alternateAlignment: go.TreeLayout.AlignmentStart,
                    alternateNodeIndent: 15,
                    alternateNodeIndentPastParent: 1,
                    alternateNodeSpacing: 15,
                    alternateLayerSpacing: 40,
                    alternateLayerSpacingParentOverlap: 1,
                    alternatePortSpot: new go.Spot(0.001, 1, 20, 0),
                    alternateChildPortSpot: go.Spot.Left
                  })
            });
    
        // This function provides a common style for most of the TextBlocks.
        // Some of these values may be overridden in a particular TextBlock.
        function textStyle(field) {
          return [
            {
              font: "12px Roboto, sans-serif", stroke: "rgba(0, 0, 0, .60)",
              visible: false  // only show textblocks when there is corresponding data for them
            },
            new go.Binding("visible", field, val => val !== undefined)
          ];
        }
    
        // define Converters to be used for Bindings
        function theNationFlagConverter(nation) {
          return "https://www.nwoods.com/images/emojiflags/" + nation + ".png";
        }
    
        // define the Node template
        myDiagram.nodeTemplate =
          $(go.Node, "Auto",
            {
              locationSpot: go.Spot.Top,
              isShadowed: true, shadowBlur: 1,
              shadowOffset: new go.Point(0, 1),
              shadowColor: "rgba(0, 0, 0, .14)",
              selectionAdornmentTemplate:  // selection adornment to match shape of nodes
                $(go.Adornment, "Auto",
                  $(go.Shape, "RoundedRectangle", roundedRectangleParams,
                    { fill: null, stroke: "#7986cb", strokeWidth: 3 }
                  ),
                  $(go.Placeholder)
                )  // end Adornment
            },
            $(go.Shape, "RoundedRectangle", roundedRectangleParams,
              { name: "SHAPE", fill: "#ffffff", strokeWidth: 0 },
              // gold if highlighted, white otherwise
              new go.Binding("fill", "isHighlighted", h => h ? "gold" : "#ffffff").ofObject()
            ),
            $(go.Panel, "Vertical",
              $(go.Panel, "Horizontal",
                { margin: 8 },
                $(go.Picture,  // flag image, only visible if a nation is specified
                  { margin: mr8, visible: false, desiredSize: new go.Size(50, 50) },
                  new go.Binding("source", "nation", theNationFlagConverter),
                  new go.Binding("visible", "nation", nat => nat !== undefined)
                ),
                $(go.Panel, "Table",
                  $(go.TextBlock,
                    {
                      row: 0, alignment: go.Spot.Left,
                      font: "16px Roboto, sans-serif",
                      stroke: "rgba(0, 0, 0, .87)",
                      maxSize: new go.Size(160, NaN)
                    },
                    new go.Binding("text", "name")
                  ),
                  $(go.TextBlock, textStyle("title"),
                    {
                      row: 1, alignment: go.Spot.Left,
                      maxSize: new go.Size(160, NaN)
                    },
                    new go.Binding("text", "title")
                  ),
                  $("PanelExpanderButton", "INFO",
                    { row: 0, column: 1, rowSpan: 2, margin: ml8 }
                  )
                )
              ),
              $(go.Shape, "LineH",
                {
                  stroke: "rgba(0, 0, 0, .60)", strokeWidth: 1,
                  height: 1, stretch: go.GraphObject.Horizontal
                },
                new go.Binding("visible").ofObject("INFO")  // only visible when info is expanded
              ),
              $(go.Panel, "Vertical",
                {
                  name: "INFO",  // identify to the PanelExpanderButton
                  stretch: go.GraphObject.Horizontal,  // take up whole available width
                  margin: 8,
                  defaultAlignment: go.Spot.Left,  // thus no need to specify alignment on each element
                },
                $(go.TextBlock, textStyle("headOf"),
                  new go.Binding("text", "headOf", head => "Lokasi: " + head)
                ),
                $(go.TextBlock, textStyle("boss"),
                  new go.Binding("margin", "headOf", head => mt8), // some space above if there is also a headOf value
                 
                  new go.Binding("text", "boss", (boss) => {
                    var boss = myDiagram.model.findNodeDataForKey(boss);
                    if (boss !== null) {
                      return "Induk: " + boss.name ;
                    }
                    return "";
                  })
                )
              )
            )
          );
    
        // define the Link template, a simple orthogonal line
        myDiagram.linkTemplate =
          $(go.Link, go.Link.Orthogonal,
            { corner: 5, selectable: false },
            $(go.Shape, { strokeWidth: 3, stroke: "#424242" }));  // dark gray, rounded corner links
    
    
        // set up the nodeDataArray, describing each person/position
        var nodeDataArray = {!! $nodeDataArrayJson !!};
    
        // create the Model with data for the tree, and assign to the Diagram
        myDiagram.model =
          new go.TreeModel(
            {
              nodeParentKeyProperty: "boss",  // this property refers to the parent node data
              nodeDataArray: nodeDataArray
            });
    
        // Overview
        myOverview =
          new go.Overview("myOverviewDiv",  // the HTML DIV element for the Overview
            { observed: myDiagram, contentAlignment: go.Spot.Center });   // tell it which Diagram to show and pan
      }
    
      // the Search functionality highlights all of the nodes that have at least one data property match a RegExp
      function searchDiagram() {  // called by button
        var input = document.getElementById("mySearch");
        if (!input) return;
        myDiagram.focus();
    
        myDiagram.startTransaction("highlight search");
    
        if (input.value) {
          // search four different data properties for the string, any of which may match for success
          // create a case insensitive RegExp from what the user typed
          var safe = input.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
          var regex = new RegExp(safe, "i");
          var results = myDiagram.findNodesByExample({ name: regex },
            { nation: regex },
            { title: regex },
            { headOf: regex },
            { keterangan: regex });
          myDiagram.highlightCollection(results);
          // try to center the diagram at the first node that was found
          if (results.count > 0) myDiagram.centerRect(results.first().actualBounds);
        } else {  // empty string only clears highlighteds collection
          myDiagram.clearHighlighteds();
        }
    
        myDiagram.commitTransaction("highlight search");
      }
      window.addEventListener('DOMContentLoaded', init);
    </script>
    @endscript
    <div id="sample" style="position: relative;">
    <div id="myDiagramDiv" style="background-color: rgb(242, 242, 242); border: none ; width: 100%; height: 700px; position: relative; -webkit-tap-highlight-color: rgba(255, 255, 255, 0);"><canvas tabindex="0" width="818" height="853" style="position: absolute; top: 0px; left: 0px; z-index: 2; user-select: none; touch-action: none; width: 655px; height: 683px;"></canvas><div style="position: absolute; overflow: auto; width: 670px; height: 698px; z-index: 1;"><div style="position: absolute; width: 3433.65px; height: 1590.37px;"></div></div></div>
    <div id="myOverviewDiv" style="-webkit-tap-highlight-color: rgba(255, 255, 255, 0);"><canvas tabindex="0" width="198" height="98" style="position: absolute; top: 0px; left: 0px; z-index: 2; user-select: none; touch-action: none; width: 198px; height: 98px;"></canvas><div style="position: absolute; overflow: auto; width: 198px; height: 98px; z-index: 1;"><div style="position: absolute; width: 1px; height: 1px;"></div></div></div> <!-- Styled in a <style> tag at the top of the html page -->
</div>
