<li id="srm-receipt"><a href="?mode=srm.receipt.response">帳票管理</a>
  {% for item in apps.receipts %}
    {% if loop.first %}
      <ul>
    {% endif %}
    <li{% if item.active == '1' %} class="active"{% endif %}><a href="?mode=srm.receipt.response&amp;t={{ item.id }}">{{ item.title }}</a></li>
    {% if loop.last %}
      </ul>
    {% endif %}
  {% endfor %} 
</li>
<li id="srm-client"><a href="?mode=srm.client.response">取引先</a></li>
<li id="srm-bank"><a href="?mode=srm.bank.response">金融機関</a></li>
