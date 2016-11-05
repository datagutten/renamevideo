// JavaScript Document
function fill_episodes()
{
	var key;
	var count=document.getElementById('field_count');
	var episode;
	var input;
	//console.log(count.innerHTML);
	for (key=0; key<=count.innerHTML; ++key)
	{
		episode=document.getElementById('tvdb_episode'+key);
		input=document.getElementById('input'+key);
		input.setAttribute('value',episode.textContent);
	}
}