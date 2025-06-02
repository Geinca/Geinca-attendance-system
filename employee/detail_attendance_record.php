 <div class="dashboard-card p-4">
                <h5 class="mb-4"><i class="fas fa-history me-2"></i>Detailed Attendance Records</h5>
                <div class="table-responsive">
                    <table class="table table-custom table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Start Work</th>
                                <th>Break Start</th>
                                <th>Break End</th>
                                <th>Stop Work</th>
                                <th>Total Work Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $result_all->data_seek(0);
                            while ($row = $result_all->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['date']); ?></td>
                                    <td><?php echo formatTime($row['start_time']); ?></td>
                                    <td><?php echo formatTime($row['break_start_time']); ?></td>
                                    <td><?php echo formatTime($row['break_end_time']); ?></td>
                                    <td><?php echo formatTime($row['stop_time']); ?></td>
                                    <td><?php echo getTotalWorkTime($row['start_time'], $row['stop_time'], $row['break_start_time'], $row['break_end_time']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>