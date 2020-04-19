{% extends "master.tpl" %}

{% block main %}
<input type="hidden" name="mode" value="srm.receipt.receive:remove">
<div class="flex-row-box">
  <div class="grow-box">
    <div class="wrapper section-list">
      <h1>{{ receiptName }}一覧</h1>
      {% for unit in receipts %}
        <section{% if unit.unavailable == "1"%} class="unavailable"{% endif %}>
          <h2>{{ unit.subject }}</h2>
          <h3>{{ unit.company }}</h3>
          <p>No.{{ unit.receipt_number }}&nbsp;発行日：{{ unit.issue_date|date('Y年m月d日') }}</p>
          {% if typeOf == 'bill' %}
            <p class="bill-info">
            支払期限：<i>{{ unit.due_date|date('Y年m月d日') }}</i><br>
            {% if unit.collected == 1 %}
              ●入金日：<strong>未入金</strong>
            {% elseif unit.collected == 2 %}
              ○入金日：<b>入金待ち</b>
            {% elseif unit.receipt is not empty %}
              ◎入金日：<i>{{ unit.receipt|date('Y年m月d日') }}</i>
            {% endif %}
            </p>
          {% endif %}
          <nav class="controls flex-block">
            <div>
              {% if unit.draft != '1' and unit.unavailable != '1' and duplicateTo is defined %}
                {% for item in duplicateTo %}
                  <a href="?mode=srm.receipt.response:edit&id={{ unit.issue_date|date('Y-m-d') ~ ':' ~ unit.receipt_number }}&amp;cp={{ item.id }}">{{ item.label }}</a>
                {% endfor %}
              {% endif %}
            </div>
            <div>
              {% if apps.hasPermission('srm.update') %} 
              <a href="?mode=srm.receipt.response:edit&id={{ unit.issue_date|date('Y-m-d') ~ ':' ~ unit.receipt_number }}{% if unit.draft == '1'%}&amp;draft=1{% endif %}">明細表示</a>
              {% endif %}
              {% if unit.unavailable == '1' %}
                <span>無効</span>
              {% elseif unit.draft != '1' %}
                <a href="?mode=srm.receipt.response:download-pdf&id={{ unit.issue_date|date('Y-m-d') ~ ':' ~ unit.receipt_number }}" target="_blank">PDF</a>
              {% else %}
                <span>下書き</span>
              {% endif %}
            </div>
          </nav>
        </section>
      {% else %}
        <p>未だ{{ receiptName }}が登録されていません</p>
      {% endfor %}
    </div>
  </div>
  <div class="fixed-box">
    <div class="explorer-list">
      <div class="footer-controls">
        <nav class="links flexbox">
          {% if apps.hasPermission('srm.create') %} 
          <a href="?mode=srm.receipt.response:edit"><mark>＋</mark>新規{{ receiptName }}作成</a>
          {% endif %}
          <input type="text" name="search_query" id="search-query-1" class="search-query"{% if queryString %} value="{{ queryString }}"{% endif %}>
          <button type="reset" class="clear-search">×</button>
        </nav>
        <nav class="pagination">
          {% include 'pagination.tpl' with {'columnCount':'9'} %}
        </nav>
      </div>
    </div>
  </div>
</div>
{% endblock %}

{% block pagefooter %}
  <script src="/script/srm/receipt.js"></script>
{% endblock %}
