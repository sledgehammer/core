/**
 * dump()
 * 
 * Shortcut naar console.debug(), maar zag geen javascript fout geven als firebug uitstaat.
 * 
 * Als firebug aanstond tijdens het runnen van dit script, maar daarna wordt uitgezet,
 * werkt de dump() functie niet meer, ook niet als je firebug weer aanzet.
 */
if (window.console !== undefined) {
	dump = console.debug;
} else {
	function dump(variable) {
		if (window.console !== undefined) { // Is firebug weer aangezet?
			console.log(variable); // soort debug(), maar zonder bestand en regelnr. 
		}
	}
}