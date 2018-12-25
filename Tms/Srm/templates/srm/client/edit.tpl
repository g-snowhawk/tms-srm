{% extends "master.tpl" %}

{% block main %}
  <p id="backlink"><a href="?mode=srm.client.response">一覧に戻る</a></p>
  <div class="wrapper">
    <h1>取引先データ編集</h1>
    {% if err.vl_company == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_company == 1 %} invalid{% endif %}">
      <label for="company">会社名</label>
      <input type="text" name="company" id="company" value="{{ post.company }}">
    </div>
    <div class="fieldset">
      <label for="division">部署</label>
      <input type="text" name="division" id="division" value="{{ post.division }}">
    </div>
    <div class="fieldset">
      <label for="fullname">担当者</label>
      <input type="text" name="fullname" id="fullname" value="{{ post.fullname }}">
    </div>
    <div class="fieldset">
      <label for="zip">郵便番号</label>
      <input type="text" name="zip" id="zip" value="{{ post.zip }}">
    </div>
    <div class="fieldset">
      <label for="state">都道府県</label>
      <input type="text" name="state" id="state" value="{{ post.state }}">
    </div>
    <div class="fieldset">
      <label for="city">市区郡</label>
      <input type="text" name="city" id="city" value="{{ post.city }}">
    </div>
    <div class="fieldset">
      <label for="town">町村名</label>
      <input type="text" name="town" id="town" value="{{ post.town }}">
    </div>
    <div class="fieldset">
      <label for="address1">番地等</label>
      <input type="text" name="address1" id="address1" value="{{ post.address1 }}">
    </div>
    <div class="fieldset">
      <label for="address2">建物名等</label>
      <input type="text" name="address2" id="address2" value="{{ post.address2 }}">
    </div>
    <div class="fieldset">
      <label for="tel">電話番号</label>
      <input type="text" name="tel" id="tel" value="{{ post.tel }}">
    </div>
    <div class="fieldset">
      <label for="fax">FAX番号</label>
      <input type="text" name="fax" id="fax" value="{{ post.fax }}">
    </div>

    <div class="metadata">
      登録日：{{ post.create_date|date('Y年n月j日 H:i') }}<input type="hidden" name="create_date" value="{{ post.create_date }}"><br>
      更新日：{{ post.modify_date|date('Y年n月j日 H:i') }}<input type="hidden" name="modify_date" value="{{ post.modify_date }}"><br>
    </div>
    <div class="form-footer">
      <input type="submit" name="s1_submit" value="登録">
      <input type="hidden" name="mode" value="srm.client.receive:save">
      <input type="hidden" name="id" value="{{ post.id }}">
    </div>
  </div>
{% endblock %}
