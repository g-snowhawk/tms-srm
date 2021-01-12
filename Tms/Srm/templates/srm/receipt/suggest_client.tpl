{% for client in clients %}
  {% if loop.first %}
  <ul id="suggested-clients">
  {% endif %}
    <li>
      <a href="#" data-valid="{{ client.valid }}" data-term="{{ client.term }}" data-delivery="{{ client.delivery }}" data-payment="{{ client.payment }}" data-bank-id="{{ client.bank_id }}">
        <span class="client-data" id="company">{{ client.company }}</span>
        <span class="client-data" id="division">{{ client.division }}</span>
        <span class="client-data" id="fullname">{{ client.fullname }}</span>
        <span class="client-data" id="zipcode">{{ client.zipcode}}</span>
        <span class="client-data" id="address1">{{ client.address1 }}</span>
        <span class="client-data" id="address2">{{ client.address2 }}</span>
      </a>
    </li>
  {% if loop.last %}
  </ul>
  {% endif %}
{% endfor %}
