jQuery(document).ready(function($){
		
		var val_range;
var sal_range;
$.fn.dataTable.ext.search.push(
   function( settings, data, dataIndex ) {
    var min = parseFloat(sal_range.slider( "values", 0 ));
    var max = parseFloat(sal_range.slider( "values", 1 ));
	  // console.log(data[2]);
    var col = parseFloat( parseInt(data[2].split('">$')[1].split('</a>')[0]) ) || 0; // data[number] = column number
    if ( ( isNaN( min ) && isNaN( max ) ) ||
         ( isNaN( min ) && col <= max ) ||
         ( min <= col   && isNaN( max ) ) ||
         ( min <= col   && col <= max ) )
    {
      return true;
    }
    return false;
  }
);
		
		 sal_range = $( "#val_range_salary" );

  var val_range_salary =$( "#live_range_val_salary" );

    sal_range.slider({
    range: true,
  	min: 0,
  	max: 10000,
  	step: 100,
  	values: [ 0, 10000 ],
  	slide: function( event, ui ) {
      val_range_salary.val("$" + ui.values[ 0 ] + " - $" + ui.values[ 1 ] );
    },
  	stop: function( event, ui ) {
      table.draw();
    }
  });
  val_range_salary.val("$" + sal_range.slider( "values", 0 ) + " - $" + sal_range.slider( "values", 1 ) );
  
		
		
    var table = $('#example').DataTable( {
        "order": [[ 0, "asc" ]],
		"pageLength": 25,
		"dom": '<lf<"scrollx"t>ip>',
		"lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
    } );
	
} );