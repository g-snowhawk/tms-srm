{% extends "master.tpl" %}

{% block main %}
  {% set displayFooter = 0 %}
  <input type="hidden" name="mode" value="srm.client.receive:update">
  <div class="wrapper">
    <h1>取引先一覧</h1>
    {% for unit in users %}
      {% if loop.first %}
        <table class="nlist">
          <thead>
            <tr>
              <td>会社名</td>
              <td>担当者</td>
              <td>住所</td>
              <td>不可視</td>
            </tr>
          </thead>
          <tbody>
      {% endif %}
      <tr>
        <td>{{ unit.company }}</td>
        <td>{{ unit.fullname}}</td>
        <td>{{ unit.address1 ~ (unit.address2 is empty ? '' : ' ' ~ unit.address2) }}</td>
        <td class="ta-r"><input type="checkbox" name="no_suggestion[{{ unit.id }}]" value="yes"{% if unit.no_suggestion == 'yes' %} checked{% endif %} data-client-id="{{ unit.id }}"></td>
      </tr>
      {% if loop.last %}
          </tbody>
        </table>
        {% set displayFooter = 1 %}
      {% endif %}
    {% else %}
      <p>未だ取引先の登録がありません</p>
    {% endfor %}
  </div>
{% endblock %}

{% block pagefooter %}
  <script src="/script/srm/clients.js"></script>
{% endblock %}
