Subject: 請求書発行リスト
Importance: High

本日（{{ billing_date|date('Y年n月j日') }}）発行すべき請求書リストです。

{% for bill in draft_bills %}
  - 請求書No.{{ bill.number }}（{{ bill.company }}）
{% endfor %}

以上の請求書を発行してください。
