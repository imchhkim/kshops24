<<<<<<< HEAD
<table class="table table-bordered" style="--bs-table-color: #212529; --bs-table-bg: #fff; --bs-table-border-color: #dee2e6; --bs-table-accent-bg: transparent; --bs-table-striped-color: #212529; --bs-table-striped-bg: rgba(0, 0, 0, 0.05); --bs-table-active-color: #212529; --bs-table-active-bg: rgba(0, 0, 0, 0.1); --bs-table-hover-color: #212529; --bs-table-hover-bg: rgba(0, 0, 0, 0.075); width: 700px; border-color: rgb(222, 226, 230);">
    <tbody>
        <tr style="border-width: 1px 0px;">
            <td style="background-color: rgb(255, 255, 255); box-shadow: rgba(0, 0, 0, 0) 0px 0px 0px 9999px inset;">
                <div class="wrapper" style="color: rgb(33, 37, 41);">
                    <div class="main">
                        <div class="header">
                            <h1 style="color: rgb(33, 37, 41);"><span style="font-size: 2.5rem;">KShops24</span></h1>
                        </div>
                    </div>
                </div>
                <div class="wrapper" style="">
                    <div class="main" style="">
                        <div class="content" style="">
                            <h2 style="color: rgb(33, 37, 41);">안녕하세요, {{shops:shop_name}} 점주님!</h2>
                            <p style="color: rgb(33, 37, 41);"><br><span style="font-weight: bolder;">
                                    <font color="#ff0000"><span style="font-size: 16px;">점주님!!! 점주님의 상점이&nbsp;</span><span style="font-size: 16px;">KShops24</span><span style="font-size: 16px;">에서 영구 삭제될 예정입니다.</span></font>
                                </span></p>
                            <div class="highlight-box" style="color: rgb(33, 37, 41);"><span style="font-size: 16px; font-weight: 700;">KShops24&nbsp;</span><span style="font-size: 16px;">규정에 따라 <b>휴점 후 90일이 경과하면 상점은 영구 삭제</b> 됩니다. 상점 "삭제" 시,&nbsp;</span><span style="font-size: 16px;">점주님&nbsp;<span style="font-weight: bolder;">상점의 모든 정보(이미지들 포함)들은 삭제</span>&nbsp;될 것이며,&nbsp;</span><span style="font-size: 16px;">상점 "삭제" 후에는&nbsp;</span><span style="font-size: 16px; font-weight: bolder;">다른 점주가 점주님의 모든 정보를 사용할 수 있게&nbsp;</span><span style="font-size: 16px;">됩니다. </span></div>
                            <div class="highlight-box" style="color: rgb(33, 37, 41);"><span style="font-size: 16px;"><br></span></div>
                            <div class="highlight-box" style=""><span style="color: rgb(33, 37, 41); font-size: 16px;">따라서 "</span><span style="color: rgb(33, 37, 41); font-size: 16px;">삭제" 전까지 연체된 모든 사용료를 납부하시기 바랍니다.&nbsp;</span>자세한 청구 내역 확인 및 납입은 <b>관리자 대시보드의 [결제 관리] 메뉴</b>에서 하실 수 있습니다.</div>
                            <hr style="color: rgb(33, 37, 41); border-top: 1px solid rgb(33, 37, 41);">

                            <div class="info-table" style="color: rgb(33, 37, 41);"><span style="font-weight: 700; font-size: 15px; color: rgb(26, 31, 54); display: block; margin-bottom: 10px;">📋 삭제 정보</span>
                                <table width="100%" style="width: 100%;">
                                    <tbody>
                                        <tr>
                                            <td class="label" style="width: 150px;"><span style="font-weight: bolder;">삭제 예정일</span></td>
                                            <td class="value">{{shops:deleted_date}}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="info-table" style="color: rgb(33, 37, 41);"><span style="font-weight: 700; font-size: 15px; color: rgb(26, 31, 54); display: block; margin-bottom: 10px;"><br>📋 상점 정보</span>
                                <table width="100%" style="width: 100%;">
                                    <tbody>
                                        <tr>
                                            <td class="label" style="width: 150px;"><span style="font-weight: bolder;">업종 카테고리</span></td>
                                            <td class="value">{{shops:category}}</td>
                                        </tr>
                                        <tr>
                                            <td class="label" style="width: 150px;"><span style="font-weight: bolder;">상점명(한글)</span></td>
                                            <td class="value">{{shops:shop_name}}</td>
                                        </tr>
                                        <tr>
                                            <td class="label" style="width: 150px;"><span style="font-weight: bolder;">상점명(영문)</span></td>
                                            <td class="value">{{shops:shop_name_en}}<br></td>
                                        </tr>
                                        <tr>
                                            <td class="label" style="width: 150px;"><span style="font-weight: bolder;">상점접속 도메인 *</span></td>
                                            <td class="value">https://kshops24.com/{{shops:subdomain}}<br></td>
                                        </tr>
                                        <tr>
                                            <td class="label" style="width: 150px;"><span style="font-weight: bolder;">관리자 이메일</span></td>
                                            <td class="value">{{shops:manager_email}}<br></td>
                                        </tr>
                                        <tr>
                                            <td class="label" style="width: 150px;"><span style="font-weight: bolder;">대표 연락처</span></td>
                                            <td class="value">{{shops:phone_mobile}}<br></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="terms-box" style="color: rgb(33, 37, 41);"><br></div>
                            <div class="terms-box" style="color: rgb(33, 37, 41);"><span class="terms-title">📜&nbsp;<span style="font-weight: bolder;">서비스 이용 약관 및 면책 조항&nbsp;<a href="https://kshops24.com/common/terms_of_use.php" target="_blank">바로가기&nbsp;</a></span></span>
                                <div class="terms-text">
                                    <p><br></p>
                                </div>
                            </div>
                        </div>
                        <div class="footer" style="color: rgb(33, 37, 41);">
                            <p><br></p>
                        </div>
                    </div>
                </div>
                <div style="color: rgb(136, 136, 136); background-color: rgb(248, 249, 250); padding: 20px; text-align: center; font-size: 12px; border-top: 1px solid rgb(238, 238, 238);">본 메일은 발신 전용입니다. 문의사항은 KShops24 고객센터를 이용해 주세요.<br>© 2026 KShops24. All rights reserved.</div><br>
            </td>
        </tr>
    </tbody>
=======
<table class="table table-bordered" style="--bs-table-color: #212529; --bs-table-bg: #fff; --bs-table-border-color: #dee2e6; --bs-table-accent-bg: transparent; --bs-table-striped-color: #212529; --bs-table-striped-bg: rgba(0, 0, 0, 0.05); --bs-table-active-color: #212529; --bs-table-active-bg: rgba(0, 0, 0, 0.1); --bs-table-hover-color: #212529; --bs-table-hover-bg: rgba(0, 0, 0, 0.075); width: 700px; border-color: rgb(222, 226, 230);">
    <tbody>
        <tr style="border-width: 1px 0px;">
            <td style="background-color: rgb(255, 255, 255); box-shadow: rgba(0, 0, 0, 0) 0px 0px 0px 9999px inset;">
                <div class="wrapper" style="color: rgb(33, 37, 41);">
                    <div class="main">
                        <div class="header">
                            <h1 style="color: rgb(33, 37, 41);"><span style="font-size: 2.5rem;">KShops24</span></h1>
                        </div>
                    </div>
                </div>
                <div class="wrapper" style="">
                    <div class="main" style="">
                        <div class="content" style="">
                            <h2 style="color: rgb(33, 37, 41);">안녕하세요, {{shops:shop_name}} 점주님!</h2>
                            <p style="color: rgb(33, 37, 41);"><br><span style="font-weight: bolder;">
                                    <font color="#ff0000"><span style="font-size: 16px;">점주님!!! 점주님의 상점이&nbsp;</span><span style="font-size: 16px;">KShops24</span><span style="font-size: 16px;">에서 영구 삭제될 예정입니다.</span></font>
                                </span></p>
                            <div class="highlight-box" style="color: rgb(33, 37, 41);"><span style="font-size: 16px; font-weight: 700;">KShops24&nbsp;</span><span style="font-size: 16px;">규정에 따라 <b>휴점 후 90일이 경과하면 상점은 영구 삭제</b> 됩니다. 상점 "삭제" 시,&nbsp;</span><span style="font-size: 16px;">점주님&nbsp;<span style="font-weight: bolder;">상점의 모든 정보(이미지들 포함)들은 삭제</span>&nbsp;될 것이며,&nbsp;</span><span style="font-size: 16px;">상점 "삭제" 후에는&nbsp;</span><span style="font-size: 16px; font-weight: bolder;">다른 점주가 점주님의 모든 정보를 사용할 수 있게&nbsp;</span><span style="font-size: 16px;">됩니다. </span></div>
                            <div class="highlight-box" style="color: rgb(33, 37, 41);"><span style="font-size: 16px;"><br></span></div>
                            <div class="highlight-box" style=""><span style="color: rgb(33, 37, 41); font-size: 16px;">따라서 "</span><span style="color: rgb(33, 37, 41); font-size: 16px;">삭제" 전까지 연체된 모든 사용료를 납부하시기 바랍니다.&nbsp;</span>자세한 청구 내역 확인 및 납입은 <b>관리자 대시보드의 [결제 관리] 메뉴</b>에서 하실 수 있습니다.</div>
                            <hr style="color: rgb(33, 37, 41); border-top: 1px solid rgb(33, 37, 41);">

                            <div class="info-table" style="color: rgb(33, 37, 41);"><span style="font-weight: 700; font-size: 15px; color: rgb(26, 31, 54); display: block; margin-bottom: 10px;">📋 삭제 정보</span>
                                <table width="100%" style="width: 100%;">
                                    <tbody>
                                        <tr>
                                            <td class="label" style="width: 150px;"><span style="font-weight: bolder;">삭제 예정일</span></td>
                                            <td class="value">{{shops:deleted_date}}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="info-table" style="color: rgb(33, 37, 41);"><span style="font-weight: 700; font-size: 15px; color: rgb(26, 31, 54); display: block; margin-bottom: 10px;"><br>📋 상점 정보</span>
                                <table width="100%" style="width: 100%;">
                                    <tbody>
                                        <tr>
                                            <td class="label" style="width: 150px;"><span style="font-weight: bolder;">업종 카테고리</span></td>
                                            <td class="value">{{shops:category}}</td>
                                        </tr>
                                        <tr>
                                            <td class="label" style="width: 150px;"><span style="font-weight: bolder;">상점명(한글)</span></td>
                                            <td class="value">{{shops:shop_name}}</td>
                                        </tr>
                                        <tr>
                                            <td class="label" style="width: 150px;"><span style="font-weight: bolder;">상점명(영문)</span></td>
                                            <td class="value">{{shops:shop_name_en}}<br></td>
                                        </tr>
                                        <tr>
                                            <td class="label" style="width: 150px;"><span style="font-weight: bolder;">상점접속 도메인 *</span></td>
                                            <td class="value">https://kshops24.com/{{shops:subdomain}}<br></td>
                                        </tr>
                                        <tr>
                                            <td class="label" style="width: 150px;"><span style="font-weight: bolder;">관리자 이메일</span></td>
                                            <td class="value">{{shops:manager_email}}<br></td>
                                        </tr>
                                        <tr>
                                            <td class="label" style="width: 150px;"><span style="font-weight: bolder;">대표 연락처</span></td>
                                            <td class="value">{{shops:phone_mobile}}<br></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="terms-box" style="color: rgb(33, 37, 41);"><br></div>
                            <div class="terms-box" style="color: rgb(33, 37, 41);"><span class="terms-title">📜&nbsp;<span style="font-weight: bolder;">서비스 이용 약관 및 면책 조항&nbsp;<a href="https://kshops24.com/common/terms_of_use.php" target="_blank">바로가기&nbsp;</a></span></span>
                                <div class="terms-text">
                                    <p><br></p>
                                </div>
                            </div>
                        </div>
                        <div class="footer" style="color: rgb(33, 37, 41);">
                            <p><br></p>
                        </div>
                    </div>
                </div>
                <div style="color: rgb(136, 136, 136); background-color: rgb(248, 249, 250); padding: 20px; text-align: center; font-size: 12px; border-top: 1px solid rgb(238, 238, 238);">본 메일은 발신 전용입니다. 문의사항은 KShops24 고객센터를 이용해 주세요.<br>© 2026 KShops24. All rights reserved.</div><br>
            </td>
        </tr>
    </tbody>
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
</table>