{extends "$layout"}

{block name="content"}
    <section>
        <p>{l s='Something went wrong, contact us.' mod='pagofacil'}</p>
        <br>
        <p>{l s='Error: ' mod='pagofacil'}{$errorCode}</p>
    </section>
{/block}
